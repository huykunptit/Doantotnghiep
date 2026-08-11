"""
Tutoring Router — gợi ý học tập cá nhân hóa.
Phase 4.
"""

from __future__ import annotations

from fastapi import APIRouter

from models.schemas import (
    StudyAdvisorRequest,
    StudyAdvisorResponse,
    TutoringRequest,
    TutoringResponse,
)
from services import tutoring_service
from utils.providers import require_api_key

router = APIRouter(prefix="/tutoring", tags=["Smart Tutoring"])


@router.post("/recommend", response_model=TutoringResponse)
async def get_recommendations(payload: TutoringRequest) -> TutoringResponse:
    """
    Gợi ý học tập cá nhân hóa dựa trên tiến độ sinh viên.

    Trả về:
    - Bài học nên ôn lại
    - Khóa học tiếp theo
    - Kỹ năng yếu
    - Mẹo học tập
    - Tóm tắt tình hình
    """
    require_api_key(payload.provider, payload.api_key)
    return await tutoring_service.get_recommendations(payload)


@router.post("/study-advisor", response_model=StudyAdvisorResponse)
async def get_study_advice(payload: StudyAdvisorRequest) -> StudyAdvisorResponse:
    """
    Diễn giải kết quả đánh giá CTĐT (rule-based, tính sẵn bên Laravel) thành lời tư vấn
    học tập tự nhiên — khác với /recommend ở trên (dùng % tiến độ chung cho widget mẹo
    học bài), endpoint này dùng GPA/môn yếu/điểm quiz thật cho trang Cố vấn học tập.
    """
    require_api_key(payload.provider, payload.api_key)
    return await tutoring_service.get_study_advice(payload)
