"""
Generate Router — sinh nội dung khóa học bằng AI.
Phase 2: Tiêu đề, mô tả, quiz, đề thi.
"""

from __future__ import annotations

from fastapi import APIRouter

from models.schemas import (
    GenerateCourseTitleRequest,
    GenerateCourseTitleResponse,
    GenerateExamRequest,
    GenerateExamResponse,
    GenerateLessonDescriptionRequest,
    GenerateLessonDescriptionResponse,
    GenerateQuizRequest,
    GenerateQuizResponse,
)
from services import content_generator
from utils.providers import require_api_key

router = APIRouter(prefix="/generate", tags=["Content Generator"])


@router.post("/course-title", response_model=GenerateCourseTitleResponse)
async def generate_course_title(
    payload: GenerateCourseTitleRequest,
) -> GenerateCourseTitleResponse:
    """Sinh 5 gợi ý tiêu đề khóa học."""
    require_api_key(payload.provider, payload.api_key)
    return await content_generator.generate_course_titles(payload)


@router.post("/lesson-description", response_model=GenerateLessonDescriptionResponse)
async def generate_lesson_description(
    payload: GenerateLessonDescriptionRequest,
) -> GenerateLessonDescriptionResponse:
    """Sinh mô tả bài học."""
    require_api_key(payload.provider, payload.api_key)
    return await content_generator.generate_lesson_description(payload)


@router.post("/quiz", response_model=GenerateQuizResponse)
async def generate_quiz(
    payload: GenerateQuizRequest,
) -> GenerateQuizResponse:
    """Sinh câu hỏi trắc nghiệm từ nội dung bài học."""
    require_api_key(payload.provider, payload.api_key)
    return await content_generator.generate_quiz(payload)


@router.post("/exam", response_model=GenerateExamResponse)
async def generate_exam(
    payload: GenerateExamRequest,
) -> GenerateExamResponse:
    """Sinh đề thi hoàn chỉnh."""
    require_api_key(payload.provider, payload.api_key)
    return await content_generator.generate_exam(payload)
