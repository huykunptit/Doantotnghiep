"""
Career Router — phân tích CV và gợi ý nghề nghiệp.
"""

from __future__ import annotations

from fastapi import APIRouter

from models.schemas import (
    ParseCVRequest,
    ParseCVResponse,
    RecommendRequest,
    RecommendResponse,
)
from services import career_service

router = APIRouter(tags=["Career"])


@router.post("/parse-cv", response_model=ParseCVResponse)
def parse_cv(payload: ParseCVRequest) -> ParseCVResponse:
    """
    Parse CV và trích xuất kỹ năng.
    Hiện tại: stub (Laravel parse local).
    Phase 5: sẽ dùng AI để parse thông minh hơn.
    """
    return career_service.parse_cv(payload)


@router.post("/recommend", response_model=RecommendResponse)
def recommend(payload: RecommendRequest) -> RecommendResponse:
    """
    Gợi ý nghề nghiệp dựa trên skills và target job.
    Hiện tại: rule-based matching.
    Phase 5: sẽ nâng lên AI-powered.
    """
    return career_service.recommend(payload)
