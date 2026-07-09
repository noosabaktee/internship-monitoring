import base64
import os
from dataclasses import dataclass
from typing import List

import cv2
import numpy as np


ALGORITHM = "insightface-buffalo_l-v1"


class FaceRecognitionError(Exception):
    def __init__(self, message: str, status_code: int = 422):
        super().__init__(message)
        self.message = message
        self.status_code = status_code


@dataclass
class FaceEmbedding:
    embedding: np.ndarray
    quality: float


class FaceEngine:
    def __init__(self) -> None:
        self._app = None
        self.model_name = os.getenv("FACE_MODEL_NAME", "buffalo_l")
        self.det_size = (
            int(os.getenv("FACE_DET_WIDTH", "640")),
            int(os.getenv("FACE_DET_HEIGHT", "640")),
        )

    def _load(self):
        if self._app is not None:
            return self._app

        try:
            from insightface.app import FaceAnalysis
        except Exception as exc:
            raise FaceRecognitionError(
                "InsightFace belum terinstall. Jalankan npm run face:install lalu start ulang service.",
                503,
            ) from exc

        app = FaceAnalysis(
            name=self.model_name,
            providers=["CPUExecutionProvider"],
        )
        app.prepare(ctx_id=-1, det_size=self.det_size)
        self._app = app

        return app

    def extract(self, image_data: str) -> FaceEmbedding:
        app = self._load()
        image = self._decode_image(image_data)
        faces = app.get(image)

        if len(faces) == 0:
            raise FaceRecognitionError("Wajah tidak terdeteksi. Pastikan wajah terlihat jelas.")

        if len(faces) > 1:
            raise FaceRecognitionError("Terdeteksi lebih dari satu wajah. Gunakan satu orang di depan kamera.")

        face = faces[0]
        embedding = getattr(face, "normed_embedding", None)

        if embedding is None:
            embedding = getattr(face, "embedding", None)

        if embedding is None:
            raise FaceRecognitionError("Embedding wajah tidak dapat dibuat.", 500)

        embedding = self._normalize(np.asarray(embedding, dtype=np.float32))
        quality = self._quality(face, image.shape)

        if quality < 0.35:
            raise FaceRecognitionError("Kualitas wajah terlalu rendah. Perbaiki pencahayaan dan posisi kamera.")

        return FaceEmbedding(embedding=embedding, quality=quality)

    def enroll(self, images: List[str]) -> dict:
        samples = [self.extract(image) for image in images]
        embeddings = np.stack([sample.embedding for sample in samples])
        averaged = self._normalize(np.mean(embeddings, axis=0))
        quality = float(np.mean([sample.quality for sample in samples]))

        return {
            "embedding": self._to_list(averaged),
            "algorithm": ALGORITHM,
            "sample_count": len(samples),
            "quality": round(quality, 4),
        }

    def detect(self, image: str) -> dict:
        sample = self.extract(image)

        return {
            "detected": True,
            "algorithm": ALGORITHM,
            "quality": round(sample.quality, 4),
        }

    def verify(self, image: str, enrolled_embedding: List[float], threshold: float) -> dict:
        sample = self.extract(image)
        enrolled = self._normalize(np.asarray(enrolled_embedding, dtype=np.float32))
        similarity = float(np.dot(sample.embedding, enrolled))
        similarity = max(-1.0, min(1.0, similarity))
        distance = 1.0 - similarity

        return {
            "match": distance <= threshold,
            "distance": round(distance, 6),
            "similarity": round(similarity, 6),
            "threshold": threshold,
            "algorithm": ALGORITHM,
            "quality": round(sample.quality, 4),
        }

    def _decode_image(self, image_data: str) -> np.ndarray:
        payload = image_data.split(",", 1)[1] if image_data.startswith("data:") and "," in image_data else image_data

        try:
            binary = base64.b64decode(payload, validate=True)
        except Exception as exc:
            raise FaceRecognitionError("Format gambar kamera tidak valid.") from exc

        buffer = np.frombuffer(binary, dtype=np.uint8)
        image = cv2.imdecode(buffer, cv2.IMREAD_COLOR)

        if image is None:
            raise FaceRecognitionError("Gambar kamera tidak dapat dibaca.")

        return image

    def _quality(self, face, image_shape) -> float:
        image_height, image_width = image_shape[:2]
        detection_score = float(getattr(face, "det_score", 0.0) or 0.0)
        bbox = np.asarray(getattr(face, "bbox", [0, 0, 0, 0]), dtype=np.float32)
        width = max(0.0, float(bbox[2] - bbox[0]))
        height = max(0.0, float(bbox[3] - bbox[1]))
        area_ratio = (width * height) / max(1.0, float(image_width * image_height))
        area_score = min(1.0, area_ratio * 12.0)

        return round(max(0.0, min(1.0, (detection_score * 0.72) + (area_score * 0.28))), 4)

    def _normalize(self, vector: np.ndarray) -> np.ndarray:
        norm = float(np.linalg.norm(vector))

        if norm <= 0:
            raise FaceRecognitionError("Embedding wajah kosong.", 500)

        return vector / norm

    def _to_list(self, vector: np.ndarray) -> List[float]:
        return [round(float(value), 6) for value in vector.tolist()]
