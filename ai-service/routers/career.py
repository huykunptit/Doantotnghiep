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
    CareerAdvisorRequest,
    CareerAdvisorResponse,
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


@router.post("/advise", response_model=CareerAdvisorResponse)
async def advise(payload: CareerAdvisorRequest) -> CareerAdvisorResponse:
    """
    Tư vấn nghề nghiệp thông minh sử dụng LLM kết hợp CV + Học liệu + Yêu cầu thị trường.
    """
    return await career_service.recommend_with_llm(payload)
