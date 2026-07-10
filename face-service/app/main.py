from fastapi import FastAPI, HTTPException

from app.recognition import ALGORITHM, FaceEngine, FaceRecognitionError
from app.schemas import DetectRequest, DetectResponse, EnrollRequest, EnrollResponse, VerifyRequest, VerifyResponse


app = FastAPI(title="KMI Attendance Face Service", version="1.0.0")
engine = FaceEngine()


@app.get("/health")
def health():
    return {
        "status": "ok",
        "algorithm": ALGORITHM,
        "model": engine.model_name,
    }


@app.post("/enroll", response_model=EnrollResponse)
def enroll(payload: EnrollRequest):
    try:
        return engine.enroll(payload.images)
    except FaceRecognitionError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message) from exc


@app.post("/detect", response_model=DetectResponse)
def detect(payload: DetectRequest):
    try:
        return engine.detect(payload.image)
    except FaceRecognitionError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message) from exc


@app.post("/verify", response_model=VerifyResponse)
def verify(payload: VerifyRequest):
    try:
        return engine.verify(
            image=payload.image,
            enrolled_embedding=payload.enrolled_embedding,
            threshold=payload.threshold,
        )
    except FaceRecognitionError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message) from exc
