"""
Learning Advisor Router — Endpoints tư vấn học tập.
"""

from __future__ import annotations

from fastapi import APIRouter, HTTPException

from models.schemas import LearningAdvisorRequest, LearningAdvisorResponse
from services import learning_advisor_service

router = APIRouter(tags=["Learning Advisor"])


@router.post("/learning/advise", response_model=LearningAdvisorResponse)
async def get_learning_advice(payload: LearningAdvisorRequest) -> LearningAdvisorResponse:
    """
    Tư vấn lộ trình học tập cá nhân hóa dựa trên bảng điểm và tiến độ học.
    """
    if not payload.api_key:
        raise HTTPException(
            status_code=400,
            detail="Thiếu API key cho provider AI. Vui lòng cấu hình API key trong phần Cài đặt.",
        )

    provider = (payload.provider or "chatgpt").strip().lower()
    if provider not in {"chatgpt", "gemini", "openrouter", "claude"}:
        raise HTTPException(
            status_code=400,
            detail=f"Provider không hỗ trợ: {provider}",
        )

    response, tokens = await learning_advisor_service.generate_advice(payload)
    
    # Bổ sung token usage vào response
    # Lớp cha AIResponse có tokens_used nhưng LearningAdvisorResponse kế thừa từ BaseModel,
    # chúng ta có thể truyền thêm hoặc xử lý như response wrapper.
    # Để tương thích, chúng ta gửi trực tiếp.
    return response
