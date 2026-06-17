"""
Exam Analyzer Service — phân tích kết quả kỳ thi bằng AI.
Phase 3.
"""

from __future__ import annotations

import json
import logging

from models.schemas import ExamAnalyzeRequest, ExamAnalyzeResponse
from services.provider import call_provider
from config import settings

logger = logging.getLogger(__name__)


async def analyze_exam(payload: ExamAnalyzeRequest) -> ExamAnalyzeResponse:
    """Phân tích kết quả kỳ thi và đưa ra insights."""

    # Build thống kê câu hỏi
    questions_summary = ""
    if payload.questions_stats:
        lines = []
        for q in payload.questions_stats[:30]:  # Giới hạn 30 câu
            total = q.correct_count + q.incorrect_count + q.skip_count
            error_rate = (q.incorrect_count / total * 100) if total > 0 else 0
            lines.append(
                f"- Câu {q.question_id}: \"{q.question_text[:80]}\" — "
                f"đúng {q.correct_count}, sai {q.incorrect_count}, "
                f"bỏ qua {q.skip_count}, tỷ lệ sai {error_rate:.0f}%"
            )
        questions_summary = "\n".join(lines)

    messages = [
        {
            "role": "system",
            "content": (
                "Bạn là chuyên gia phân tích giáo dục. "
                "Nhiệm vụ: phân tích kết quả kỳ thi và đưa ra nhận xét hữu ích. "
                "Trả về JSON object với format:\n"
                "{\n"
                '  "insights": "Nhận xét tổng quan...",\n'
                '  "difficult_questions": [{"question_id": 1, "reason": "..."}],\n'
                '  "common_mistakes": ["Lỗi 1", "Lỗi 2"],\n'
                '  "recommendations_for_instructor": ["Gợi ý 1", ...],\n'
                '  "recommendations_for_students": ["Gợi ý 1", ...]\n'
                "}\n"
                "Viết bằng tiếng Việt, ngắn gọn, thực tế."
            ),
        },
        {
            "role": "user",
            "content": (
                f"Kỳ thi: {payload.exam_title}\n"
                f"Tổng thí sinh: {payload.total_students}\n"
                f"Điểm trung bình: {payload.avg_score or 'không rõ'}\n"
                f"Phân bố điểm (0-100): {payload.score_distribution}\n\n"
                f"Chi tiết câu hỏi:\n{questions_summary or 'Không có dữ liệu'}\n\n"
                "Hãy phân tích và đưa ra nhận xét. Trả về JSON."
            ),
        },
    ]

    reply, _ = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
        temperature=0.3,
    )

    return _parse_analysis(reply)


def _parse_analysis(text: str | None) -> ExamAnalyzeResponse:
    """Parse JSON analysis từ AI response."""
    if not text:
        return ExamAnalyzeResponse(insights="Không thể phân tích — AI không trả kết quả.")
    try:
        start = text.find("{")
        end = text.rfind("}")
        if start != -1 and end != -1:
            raw = json.loads(text[start : end + 1])
            return ExamAnalyzeResponse(
                insights=raw.get("insights", ""),
                difficult_questions=raw.get("difficult_questions", []),
                common_mistakes=raw.get("common_mistakes", []),
                recommendations_for_instructor=raw.get("recommendations_for_instructor", []),
                recommendations_for_students=raw.get("recommendations_for_students", []),
            )
    except (json.JSONDecodeError, ValueError) as e:
        logger.warning("Failed to parse exam analysis JSON: %s", e)
    return ExamAnalyzeResponse(insights=text)
