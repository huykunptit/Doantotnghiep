"""
Learning Advisor Service — Phân tích dữ liệu học tập cá nhân hóa và đưa ra khuyến nghị.
"""

from __future__ import annotations

import json
import logging
from typing import Any

from models.schemas import (
    LearningAdvisorRequest,
    LearningAdvisorResponse,
    StudyPlanItem,
    TokenUsage,
)
from services.provider import call_provider
from services.rag import retriever

logger = logging.getLogger(__name__)


async def generate_advice(
    payload: LearningAdvisorRequest,
) -> tuple[LearningAdvisorResponse, TokenUsage]:
    """
    Sinh khuyến nghị học tập cá nhân hóa dùng LLM.
    """
    # 1. Chuẩn bị dữ liệu học tập thành chuỗi văn bản cho prompt
    grade_transcript_str = "\n".join([
        f"- {g.course_title}: Điểm {g.final_score} ({g.grade_letter or 'N/A'}), Tín chỉ: {g.credits}, Kỳ học: {g.term_number or 'N/A'}"
        for g in payload.grade_transcript
    ])

    enrolled_courses_str = "\n".join([
        f"- {c.course_title}: Tiến độ {c.progress_percent}%, Điểm quiz trung bình: {c.quiz_avg_score or 'Chưa làm'}"
        for c in payload.enrolled_courses
    ])

    quiz_performance_str = "\n".join([
        f"- Quiz {q.quiz_title}: Điểm cao nhất {q.score}, Trạng thái: {'Đã qua' if q.passed else 'Chưa qua'}, Số lần thử: {q.attempts}"
        for q in payload.quiz_performance
    ])

    curriculum_gaps_str = "\n".join([
        f"- {gap.course_title} (Tín chỉ: {gap.credits}, Dự kiến học kỳ: {gap.term_number or 'N/A'}) - Bắt buộc"
        for gap in payload.curriculum_gaps
    ])

    # 2. Xây dựng prompt chi tiết yêu cầu LLM phân tích và trả về structured JSON
    prompt = f"""
Bạn là chuyên gia tư vấn học tập AI tại Học viện Công nghệ Bưu chính Viễn thông (PTIT).
Nhiệm vụ của bạn là phân tích hồ sơ học tập của sinh viên sau và đưa ra những đánh giá, khuyến nghị học tập mang tính hành động cao, cá nhân hóa.

THÔNG TIN SINH VIÊN:
- Tên sinh viên: {payload.student_name or "Sinh viên"}
- Ngành học: {payload.major or "Chưa khai báo"}
- Chương trình đào tạo: {payload.program or "Chưa khai báo"}
- Kỳ học hiện tại: Học kỳ {payload.current_term or 1}
- Điểm trung bình tích lũy (GPA): {payload.gpa or 0.0}/10.0
- Số tín chỉ tích lũy: {payload.total_credits_earned or 0}
- Số môn học đã hoàn thành: {payload.total_completed_courses or 0}

KẾT QUẢ CÁC MÔN HỌC CHÍNH QUY (TRANSCRIPT):
{grade_transcript_str or "Chưa có điểm môn nào."}

TIẾN ĐỘ HỌC TẬP CÁC KHÓA HỌC E-LEARNING:
{enrolled_courses_str or "Chưa đăng ký khóa học e-learning nào."}

KẾT QUẢ LÀM BÀI TRẮC NGHIỆM (QUIZ):
{quiz_performance_str or "Chưa làm bài trắc nghiệm nào."}

CÁC MÔN HỌC BẮT BUỘC CHƯA HOÀN THÀNH TRONG CHƯƠNG TRÌNH ĐÀO TẠO:
{curriculum_gaps_str or "Đã hoàn thành toàn bộ môn bắt buộc hiện tại."}

YÊU CẦU:
Hãy phân tích dữ liệu trên và trả về kết quả dưới dạng JSON có cấu trúc chính xác như sau:
{{
  "overall_assessment": "Đánh giá tổng quan về năng lực, tinh thần và tiến độ học tập hiện tại của sinh viên.",
  "strengths": [
    "Điểm mạnh 1 (Ví dụ: Học tốt các môn lập trình, tiến độ e-learning nhanh...)"
  ],
  "weaknesses": [
    "Điểm yếu hoặc điểm cần cải thiện (Ví dụ: GPA môn toán còn thấp, hay trượt quiz...)"
  ],
  "recommended_courses": [
    "Tên khóa học đề xuất để học tiếp (Nên gợi ý các môn bắt buộc chưa hoàn thành hoặc khóa học liên quan kỹ năng yếu)"
  ],
  "skills_to_develop": [
    "Kỹ năng cần phát triển thêm (Ví dụ: Lập trình OOP, Cấu trúc dữ liệu, Giải thuật...)"
  ],
  "study_plan": [
    {{
      "title": "Tên đầu việc cụ thể (Ví dụ: Ôn tập lại kiến thức môn CTDL & GT)",
      "description": "Chi tiết hành động cần làm",
      "priority": "high",
      "type": "review"
    }}
  ],
  "next_term_suggestions": [
    "Gợi ý đăng ký môn học cho học kỳ tiếp theo dựa trên curriculum gaps"
  ],
  "motivational_message": "Lời chúc, lời khuyên động viên sinh viên học tập tốt."
}}

CHÚ Ý:
- Chỉ trả về chuỗi JSON thô, không nằm trong block markdown (như ```json), không có khoảng trắng thừa đầu/cuối.
- Tất cả các trường thông tin phải bằng tiếng Việt tự nhiên và có chiều sâu.
"""

    messages = [
        {
            "role": "system",
            "content": "Bạn là chuyên gia tư vấn học tập AI. Bạn chỉ trả về định dạng JSON thuần túy theo cấu trúc được yêu cầu.",
        },
        {"role": "user", "content": prompt},
    ]

    # 3. Gọi provider AI
    reply, tokens = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    )

    if not reply:
        logger.error("Learning advisor AI response is empty")
        return LearningAdvisorResponse(overall_assessment="Không thể tạo phân tích lúc này."), tokens

    # 4. Parse JSON response từ LLM
    try:
        # Làm sạch response đề phòng LLM tự ý thêm markdown code block
        cleaned_reply = reply.strip()
        if cleaned_reply.startswith("```json"):
            cleaned_reply = cleaned_reply[7:]
        if cleaned_reply.endswith("```"):
            cleaned_reply = cleaned_reply[:-3]
        cleaned_reply = cleaned_reply.strip()

        data = json.loads(cleaned_reply)

        study_plan = []
        for item in data.get("study_plan", []):
            study_plan.append(StudyPlanItem(
                title=item.get("title", ""),
                description=item.get("description", ""),
                priority=item.get("priority", "medium"),
                type=item.get("type", "course")
            ))

        response = LearningAdvisorResponse(
            overall_assessment=data.get("overall_assessment", ""),
            strengths=data.get("strengths", []),
            weaknesses=data.get("weaknesses", []),
            recommended_courses=data.get("recommended_courses", []),
            skills_to_develop=data.get("skills_to_develop", []),
            study_plan=study_plan,
            next_term_suggestions=data.get("next_term_suggestions", []),
            motivational_message=data.get("motivational_message", "")
        )

        return response, tokens

    except Exception as e:
        logger.error(f"Failed to parse Learning Advisor JSON response: {e}. Raw reply: {reply}")
        # Trả về fallback object nếu parse lỗi
        return LearningAdvisorResponse(
            overall_assessment=f"Đã xảy ra lỗi khi phân tích dữ liệu học tập: {str(e)}",
            motivational_message="Hãy thử lại hoặc liên hệ với bộ phận hỗ trợ kỹ thuật."
        ), tokens
