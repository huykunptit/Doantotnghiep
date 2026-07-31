"""
Career Router — phân tích CV và gợi ý nghề nghiệp.
"""

from __future__ import annotations

from fastapi import APIRouter

from models.schemas import (
    EvaluateCVRequest,
    EvaluateCVResponse,
    ParseCVRequest,
    ParseCVResponse,
    RecommendRequest,
    RecommendResponse,
)
from services import career_service

router = APIRouter(tags=["Career"])


@router.post("/parse-cv", response_model=ParseCVResponse)
async def parse_cv(payload: ParseCVRequest) -> ParseCVResponse:
    """Parse CV: pdfminer/pypdf/docx; PDF scan → OCR AI nếu có api_key."""
    return await career_service.parse_cv(payload)


@router.post("/evaluate-cv", response_model=EvaluateCVResponse)
async def evaluate_cv(payload: EvaluateCVRequest) -> EvaluateCVResponse:
    """Đánh giá CV kiểu nhà tuyển dụng: điểm mạnh/yếu, cần cải thiện."""
    return await career_service.evaluate_cv(payload)


@router.post("/recommend", response_model=RecommendResponse)
async def recommend(payload: RecommendRequest) -> RecommendResponse:
    """
    Gợi ý nghề nghiệp dựa trên skills và target job.
    Có api_key → AI; không có / lỗi → rule-based.
    """
    return await career_service.recommend(payload)
