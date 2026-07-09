from typing import List

from pydantic import BaseModel, Field


class EnrollRequest(BaseModel):
    images: List[str] = Field(min_length=1, max_length=5)


class EnrollResponse(BaseModel):
    embedding: List[float]
    algorithm: str
    sample_count: int
    quality: float


class DetectRequest(BaseModel):
    image: str


class DetectResponse(BaseModel):
    detected: bool
    algorithm: str
    quality: float


class VerifyRequest(BaseModel):
    image: str
    enrolled_embedding: List[float] = Field(min_length=64)
    threshold: float = Field(default=0.38, ge=0.1, le=1.5)


class VerifyResponse(BaseModel):
    match: bool
    distance: float
    similarity: float
    threshold: float
    algorithm: str
    quality: float
