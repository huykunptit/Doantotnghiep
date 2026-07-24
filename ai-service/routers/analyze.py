"""
Analyze Router — phân tích kỳ thi bằng AI.
Phase 3.
"""

from __future__ import annotations

from fastapi import APIRouter

from models.schemas import ExamAnalyzeRequest, ExamAnalyzeResponse
from services import exam_analyzer
from utils.providers import require_api_key

router = APIRouter(prefix="/analyze", tags=["Exam Analytics"])


@router.post("/exam", response_model=ExamAnalyzeResponse)
async def analyze_exam(payload: ExamAnalyzeRequest) -> ExamAnalyzeResponse:
    """
    Phân tích kết quả kỳ thi.

    Trả về:
    - Insights tổng quan
    - Câu hỏi khó nhất
    - Lỗi phổ biến
    - Gợi ý cho giảng viên
    - Gợi ý cho sinh viên
    """
    require_api_key(payload.provider, payload.api_key)
    return await exam_analyzer.analyze_exam(payload)
