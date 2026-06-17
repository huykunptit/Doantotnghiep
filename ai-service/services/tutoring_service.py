"""
Tutoring Service — gợi ý học tập cá nhân hóa bằng AI.
Phase 4.
"""

from __future__ import annotations

import json
import logging

from models.schemas import TutoringRequest, TutoringResponse
from services.provider import call_provider

logger = logging.getLogger(__name__)


async def get_recommendations(payload: TutoringRequest) -> TutoringResponse:
    """Phân tích tiến độ học tập và đưa ra gợi ý cá nhân hóa."""

    # Build tóm tắt tiến độ
    progress_lines = []
    for p in payload.enrolled_courses[:10]:
        score_info = f", quiz TB: {p.quiz_avg_score:.0f}%" if p.quiz_avg_score is not None else ""
        progress_lines.append(
            f"- {p.course_title}: tiến độ {p.progress_percent:.0f}%{score_info}"
        )
    progress_summary = "\n".join(progress_lines) or "Chưa đăng ký khóa nào."

    # Build thống kê quiz
    quiz_lines = []
    for qs in payload.quiz_scores[:10]:
        quiz_lines.append(
            f"- Quiz \"{qs.get('title', '?')}\": {qs.get('score', '?')}%"
        )
    quiz_summary = "\n".join(quiz_lines) or "Chưa làm quiz nào."

    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là trợ lý học tập AI thông minh. "
                "Nhiệm vụ: phân tích tiến độ học tập của sinh viên và đưa ra gợi ý cá nhân hóa. "
                "Trả về JSON object với format:\n"
                "{\n"
                '  "review_lessons": ["Bài nên ôn 1", ...],\n'
                '  "next_courses": ["Khóa tiếp theo nên học 1", ...],\n'
                '  "weak_skills": ["Kỹ năng yếu 1", ...],\n'
                '  "study_tips": ["Mẹo học 1", ...],\n'
                '  "summary": "Tóm tắt tình hình học tập..."\n'
                "}\n"
                "Viết bằng tiếng Việt, thân thiện, khích lệ."
            ),
        },
        {
            "role": "user",
            "content": (
                f"Tiến độ khóa học:\n{progress_summary}\n\n"
                f"Kết quả quiz:\n{quiz_summary}\n\n"
                "Hãy phân tích và đưa ra gợi ý học tập. Trả về JSON."
            ),
        },
    ]

    reply, _ = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
        temperature=0.5,
    )

    return _parse_tutoring(reply)


def _parse_tutoring(text: str | None) -> TutoringResponse:
    """Parse JSON từ AI response."""
    if not text:
        return TutoringResponse(summary="Không thể phân tích — AI không trả kết quả.")
    try:
        start = text.find("{")
        end = text.rfind("}")
        if start != -1 and end != -1:
            raw = json.loads(text[start : end + 1])
            return TutoringResponse(
                review_lessons=raw.get("review_lessons", []),
                next_courses=raw.get("next_courses", []),
                weak_skills=raw.get("weak_skills", []),
                study_tips=raw.get("study_tips", []),
                summary=raw.get("summary", ""),
            )
    except (json.JSONDecodeError, ValueError) as e:
        logger.warning("Failed to parse tutoring JSON: %s", e)
    return TutoringResponse(summary=text)
