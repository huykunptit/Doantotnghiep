"""
Content Generator Service — sinh nội dung khóa học bằng AI.
Phase 2: Tiêu đề, mô tả, quiz, đề thi.
"""

from __future__ import annotations

import json
import logging

from models.schemas import (
    GeneratedQuestion,
    GenerateCourseTitleRequest,
    GenerateCourseTitleResponse,
    GenerateExamRequest,
    GenerateExamResponse,
    GenerateLessonDescriptionRequest,
    GenerateLessonDescriptionResponse,
    GenerateQuizRequest,
    GenerateQuizResponse,
    QuestionOption,
)
from services.provider import call_provider
from config import settings

logger = logging.getLogger(__name__)


async def generate_course_titles(
    payload: GenerateCourseTitleRequest,
) -> GenerateCourseTitleResponse:
    """Sinh 5 gợi ý tiêu đề khóa học từ AI."""
    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là chuyên gia thiết kế khóa học trực tuyến. "
                "Nhiệm vụ: tạo tiêu đề khóa học hấp dẫn, chuyên nghiệp bằng tiếng Việt. "
                "Trả về JSON array gồm 5 tiêu đề, không giải thích thêm. "
                'Ví dụ: ["Tiêu đề 1", "Tiêu đề 2", ...]'
            ),
        },
        {
            "role": "user",
            "content": (
                f"Danh mục: {payload.category}\n"
                f"Từ khóa: {', '.join(payload.keywords) if payload.keywords else 'không có'}\n"
                f"Đối tượng: {payload.target_audience or 'sinh viên đại học'}\n\n"
                "Hãy gợi ý 5 tiêu đề khóa học phù hợp. Trả về JSON array."
            ),
        },
    ]

    reply, _ = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
        temperature=settings.GENERATION_TEMPERATURE,
    )

    titles = _parse_json_array(reply, fallback_count=5)
    return GenerateCourseTitleResponse(titles=titles)


async def generate_lesson_description(
    payload: GenerateLessonDescriptionRequest,
) -> GenerateLessonDescriptionResponse:
    """Sinh mô tả bài học từ AI."""
    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là chuyên gia viết nội dung khóa học. "
                "Nhiệm vụ: viết mô tả bài học ngắn gọn, hấp dẫn bằng tiếng Việt. "
                "Mô tả nên bao gồm: nội dung chính, mục tiêu học tập, kỹ năng đạt được. "
                "Độ dài: 2-4 đoạn, khoảng 100-200 từ. "
                "Chỉ trả về nội dung mô tả, không cần tiêu đề hay ghi chú thêm."
            ),
        },
        {
            "role": "user",
            "content": (
                f"Khóa học: {payload.course_title}\n"
                f"Tên bài học: {payload.lesson_title}\n"
                f"Ngữ cảnh chương: {payload.section_context or 'không có'}\n\n"
                "Hãy viết mô tả cho bài học này."
            ),
        },
    ]

    reply, _ = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
        temperature=settings.GENERATION_TEMPERATURE,
    )

    return GenerateLessonDescriptionResponse(description=reply or "")


async def generate_quiz(
    payload: GenerateQuizRequest,
) -> GenerateQuizResponse:
    """Sinh câu hỏi trắc nghiệm từ nội dung bài học."""
    type_map = {
        "single_choice": "trắc nghiệm 1 đáp án đúng",
        "multiple_choice": "trắc nghiệm nhiều đáp án đúng",
        "true_false": "Đúng/Sai",
        "fill_blank": "điền từ",
    }
    types_desc = ", ".join(
        type_map.get(t, t) for t in payload.question_types
    )

    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là chuyên gia tạo câu hỏi kiểm tra. "
                "Nhiệm vụ: tạo câu hỏi từ nội dung bài học, đảm bảo chính xác và có tính giáo dục. "
                "Trả về JSON array, mỗi phần tử có format:\n"
                '{"question": "...", "type": "single_choice|multiple_choice|true_false|fill_blank", '
                '"difficulty": "easy|medium|hard", '
                '"options": [{"text": "...", "is_correct": true/false}, ...], '
                '"explanation": "..."}\n'
                "Chỉ trả JSON, không giải thích thêm."
            ),
        },
        {
            "role": "user",
            "content": (
                f"Tên bài học: {payload.lesson_title or 'Không rõ'}\n"
                f"Nội dung bài học:\n{payload.lesson_content[:3000]}\n\n"
                f"Yêu cầu: Tạo {payload.count} câu hỏi, độ khó: {payload.difficulty}, "
                f"loại câu hỏi: {types_desc}.\n"
                "Trả về JSON array."
            ),
        },
    ]

    reply, _ = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
        temperature=settings.GENERATION_TEMPERATURE,
    )

    questions = _parse_questions_json(reply)
    return GenerateQuizResponse(questions=questions)


async def generate_exam(
    payload: GenerateExamRequest,
) -> GenerateExamResponse:
    """Sinh đề thi từ chủ đề và phân bố độ khó."""
    dist = payload.difficulty_distribution
    dist_desc = ", ".join(f"{k}: {v}%" for k, v in dist.items())

    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là chuyên gia tạo đề thi. "
                "Nhiệm vụ: tạo đề thi hoàn chỉnh cho một khóa học, đảm bảo bao phủ tất cả chủ đề. "
                "Trả về JSON object với format:\n"
                '{"exam_title": "...", "questions": [...]}\n'
                "Mỗi câu hỏi có format giống quiz (question, type, difficulty, options, explanation). "
                "Chỉ trả JSON, không giải thích thêm."
            ),
        },
        {
            "role": "user",
            "content": (
                f"Khóa học: {payload.course_title}\n"
                f"Chủ đề: {', '.join(payload.topics) if payload.topics else 'toàn bộ'}\n"
                f"Số câu: {payload.count}\n"
                f"Phân bố độ khó: {dist_desc}\n"
                f"Loại câu hỏi: {', '.join(payload.question_types)}\n\n"
                "Hãy tạo đề thi hoàn chỉnh. Trả về JSON."
            ),
        },
    ]

    reply, _ = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
        temperature=settings.GENERATION_TEMPERATURE,
    )

    return _parse_exam_json(reply, payload.course_title)


# =============================================================================
# Helpers — parse JSON từ AI response
# =============================================================================


def _parse_json_array(text: str | None, fallback_count: int = 5) -> list[str]:
    """Parse JSON array từ AI response, xử lý trường hợp AI trả text kèm JSON."""
    if not text:
        return []
    try:
        # Tìm JSON array trong text
        start = text.find("[")
        end = text.rfind("]")
        if start != -1 and end != -1:
            return json.loads(text[start : end + 1])
    except (json.JSONDecodeError, ValueError):
        pass
    # Fallback: tách theo dòng
    lines = [line.strip().strip("-•").strip() for line in text.strip().split("\n") if line.strip()]
    return lines[:fallback_count]


def _parse_questions_json(text: str | None) -> list[GeneratedQuestion]:
    """Parse danh sách câu hỏi từ JSON response."""
    if not text:
        return []
    try:
        start = text.find("[")
        end = text.rfind("]")
        if start != -1 and end != -1:
            raw = json.loads(text[start : end + 1])
            return [_raw_to_question(q) for q in raw if isinstance(q, dict)]
    except (json.JSONDecodeError, ValueError) as e:
        logger.warning("Failed to parse quiz JSON: %s", e)
    return []


def _parse_exam_json(text: str | None, course_title: str) -> GenerateExamResponse:
    """Parse đề thi từ JSON response."""
    if not text:
        return GenerateExamResponse(exam_title=f"Đề thi: {course_title}")
    try:
        start = text.find("{")
        end = text.rfind("}")
        if start != -1 and end != -1:
            raw = json.loads(text[start : end + 1])
            questions = [
                _raw_to_question(q)
                for q in raw.get("questions", [])
                if isinstance(q, dict)
            ]
            return GenerateExamResponse(
                exam_title=raw.get("exam_title", f"Đề thi: {course_title}"),
                questions=questions,
            )
    except (json.JSONDecodeError, ValueError) as e:
        logger.warning("Failed to parse exam JSON: %s", e)
    return GenerateExamResponse(exam_title=f"Đề thi: {course_title}")


def _raw_to_question(raw: dict) -> GeneratedQuestion:
    """Convert raw dict sang GeneratedQuestion model."""
    options = [
        QuestionOption(
            text=opt.get("text", ""),
            is_correct=bool(opt.get("is_correct", False)),
        )
        for opt in raw.get("options", [])
        if isinstance(opt, dict)
    ]
    return GeneratedQuestion(
        question=raw.get("question", ""),
        type=raw.get("type", "single_choice"),
        difficulty=raw.get("difficulty", "medium"),
        options=options,
        explanation=raw.get("explanation"),
    )
