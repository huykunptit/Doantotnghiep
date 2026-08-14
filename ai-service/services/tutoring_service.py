"""
Tutoring Service — gợi ý học tập cá nhân hóa bằng AI.
Phase 4.
"""

from __future__ import annotations

import logging

from models.schemas import (
    StudyAdvisorRequest,
    StudyAdvisorResponse,
    TutoringRequest,
    TutoringResponse,
)
from utils.grounding import unknown_quoted_titles
from utils.json_call import complete_json

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
    short_messages = [
        {
            "role": "system",
            "content": (
                'Chỉ trả JSON thuần: {"review_lessons":[],"next_courses":[],'
                '"weak_skills":[],"study_tips":[],"summary":"..."}. Không markdown.'
            ),
        },
        {
            "role": "user",
            "content": f"Tiến độ:\n{progress_summary[:800]}\nQuiz:\n{quiz_summary[:400]}\nJSON ngắn.",
        },
    ]

    raw = await complete_json(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        model=payload.model,
        messages=messages,
        short_messages=short_messages,
        temperature=0.5,
        is_valid=lambda d: bool(str(d.get("summary") or "").strip() or d.get("study_tips")),
    )
    return TutoringResponse(
        review_lessons=raw.get("review_lessons", []) or [],
        next_courses=raw.get("next_courses", []) or [],
        weak_skills=raw.get("weak_skills", []) or [],
        study_tips=raw.get("study_tips", []) or [],
        summary=str(raw.get("summary") or ""),
    )


async def get_study_advice(payload: StudyAdvisorRequest) -> StudyAdvisorResponse:
    """Diễn giải kết quả đánh giá CTĐT (rule-based) thành lời khuyên tự nhiên, cá nhân hóa."""
    summary = payload.academic_summary or {}
    level = summary.get("level", "")
    gpa = summary.get("overall_gpa")
    completion = summary.get("completion_ratio")
    completion_pct = f"{completion * 100:.0f}%" if isinstance(completion, (int, float)) else "?"

    strengths_lines = [
        f"- {c.get('title', '?')}: {c.get('final_score', '?')}" for c in payload.strengths[:5]
    ]
    weaknesses_lines = [
        f"- {c.get('title', '?')}: {c.get('final_score', '?')}" for c in payload.weaknesses[:5]
    ]
    quiz_lines = [
        f"- Quiz \"{q.get('title', '?')}\": {q.get('score', '?')}%" for q in payload.quiz_scores[:10]
    ]

    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là cố vấn học tập AI của một trường đại học, đang tư vấn cho sinh viên dựa trên "
                "kết quả học tập THẬT (GPA, môn đã hoàn thành, môn điểm thấp, điểm quiz). "
                "Viết lời khuyên tự nhiên, cụ thể, khích lệ nhưng thẳng thắn — diễn giải ý nghĩa của "
                "số liệu thay vì chỉ lặp lại chúng, và đề xuất hành động cụ thể tiếp theo. "
                "Trả về JSON thuần (không markdown) đúng schema:\n"
                "{\n"
                '  "narrative": "đoạn tư vấn 4-7 câu tiếng Việt",\n'
                '  "study_tips": ["lời khuyên hành động cụ thể 1", ...] tối đa 5,\n'
                '  "focus_courses": ["môn/kỹ năng nên ưu tiên củng cố 1", ...] tối đa 3\n'
                "}\n"
                "Chỉ dùng dữ liệu được cung cấp, không bịa thêm môn học hay điểm số không có trong đó."
            ),
        },
        {
            "role": "user",
            "content": (
                f"Trình độ hiện tại: {level or 'chưa xác định'}. "
                f"GPA tích lũy: {gpa if gpa is not None else 'chưa đủ dữ liệu'}. "
                f"Hoàn thành {completion_pct} môn bắt buộc trong CTĐT.\n\n"
                "Môn điểm cao (điểm mạnh):\n" + ("\n".join(strengths_lines) or "Chưa có.") + "\n\n"
                "Môn điểm thấp (cần củng cố):\n" + ("\n".join(weaknesses_lines) or "Chưa có.") + "\n\n"
                "Kết quả quiz gần đây:\n" + ("\n".join(quiz_lines) or "Chưa làm quiz nào.") + "\n\n"
                f"Định hướng nghề nghiệp: {', '.join(payload.target_roles) or 'chưa xác định'}.\n"
                f"Kỹ năng nổi bật: {', '.join(payload.top_skills) or 'chưa có dữ liệu'}.\n\n"
                "Hãy viết lời tư vấn học tập cho sinh viên này. Trả về JSON."
            ),
        },
    ]

    known_titles = [
        str(c.get("title") or "")
        for c in (list(payload.strengths) + list(payload.weaknesses))
    ]

    raw = await complete_json(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        model=payload.model,
        messages=messages,
        short_messages=[
            {
                "role": "system",
                "content": (
                    'Chỉ trả JSON thuần: {"narrative":"...","study_tips":[],"focus_courses":[]}. '
                    "Không markdown. Không bịa môn hoặc điểm không có trong dữ liệu."
                ),
            },
            {
                "role": "user",
                "content": (
                    f"GPA: {gpa if gpa is not None else 'chưa có'}. "
                    f"Hoàn thành {completion_pct}. "
                    f"Môn yếu: {', '.join(c.get('title', '') for c in payload.weaknesses[:5]) or 'chưa có'}. "
                    "Viết 3-5 câu tư vấn + tối đa 3 mẹo. JSON."
                ),
            },
        ],
        temperature=0.5,
        is_valid=lambda d: bool(str(d.get("narrative") or "").strip()),
    )

    narrative = str(raw.get("narrative") or "").strip()
    unknown = unknown_quoted_titles(narrative, known_titles)
    if unknown:
        logger.warning("Study-advisor grounding: quoted titles not in profile: %s", unknown)

    return StudyAdvisorResponse(
        narrative=narrative,
        study_tips=[str(x).strip() for x in (raw.get("study_tips") or []) if str(x).strip()][:5],
        focus_courses=[str(x).strip() for x in (raw.get("focus_courses") or []) if str(x).strip()][:3],
    )

