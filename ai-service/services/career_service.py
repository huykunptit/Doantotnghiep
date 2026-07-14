"""
Career Service — phân tích CV và gợi ý nghề nghiệp.
"""

from __future__ import annotations

import logging
import re

from models.schemas import (
    ParseCVRequest,
    ParseCVResponse,
    RecommendRequest,
    RecommendResponse,
    CareerAdvisorRequest,
    CareerAdvisorResponse,
)

logger = logging.getLogger(__name__)


# =============================================================================
# Bản đồ kỹ năng theo vai trò
# =============================================================================

ROLE_SKILL_MAP: dict[str, list[str]] = {
    "laravel": ["PHP", "Laravel", "MySQL", "REST API", "Git", "Docker"],
    "php": ["PHP", "Laravel", "MySQL", "REST API", "Git", "Docker"],
    "backend": ["PHP", "Laravel", "MySQL", "REST API", "Testing", "Docker"],
    "frontend": ["JavaScript", "TypeScript", "Vue.js", "Nuxt", "HTML/CSS", "Git"],
    "full stack": ["PHP", "Laravel", "JavaScript", "Vue.js", "MySQL", "Docker"],
    "fullstack": ["PHP", "Laravel", "JavaScript", "Vue.js", "MySQL", "Docker"],
    "devops": ["Docker", "Linux", "AWS", "Git", "CI/CD", "Testing"],
    "data": ["Python", "SQL", "Machine Learning", "Testing", "Statistics"],
    "mobile": ["Flutter", "Dart", "React Native", "JavaScript", "Git"],
    "python": ["Python", "Django", "FastAPI", "SQL", "Docker", "Git"],
    "java": ["Java", "Spring Boot", "MySQL", "REST API", "Git", "Docker"],
    "react": ["React", "JavaScript", "TypeScript", "HTML/CSS", "Git", "Node.js"],
    "vue": ["Vue.js", "Nuxt", "JavaScript", "TypeScript", "HTML/CSS", "Git"],
    "ai": ["Python", "Machine Learning", "Deep Learning", "TensorFlow", "SQL"],
    "cloud": ["AWS", "Docker", "Kubernetes", "Linux", "CI/CD", "Terraform"],
}


def parse_cv(payload: ParseCVRequest) -> ParseCVResponse:
    """
    Parse CV — hiện tại trả empty vì Laravel đang tự parse local.
    Phase 5 sẽ dùng AI để parse.
    """
    # TODO Phase 5: Implement AI-powered CV parsing
    return ParseCVResponse(text="", skills=[])


def recommend(payload: RecommendRequest) -> RecommendResponse:
    """
    Gợi ý nghề nghiệp dựa trên skills và target job.
    Hiện tại: rule-based matching.
    Phase 5: sẽ nâng lên AI-powered.
    """
    normalized_job = (payload.target_job or "").strip().lower()
    skill_lookup = {skill.lower(): skill for skill in payload.skills}

    # Tìm bộ kỹ năng thị trường phù hợp
    market_skills = ["Git", "REST API", "Docker"]  # Default
    for keyword, skills in ROLE_SKILL_MAP.items():
        if keyword in normalized_job:
            market_skills = skills
            break

    current_skills_lower = set(skill_lookup.keys())
    gaps = [s for s in market_skills if s.lower() not in current_skills_lower]
    overlap = [s for s in market_skills if s.lower() in current_skills_lower]

    total = max(len(market_skills), 1)
    match_score = int(round((len(overlap) / total) * 100))
    match_score = max(35, min(match_score, 95))

    # Build summary
    summary_parts: list[str] = []
    if overlap:
        summary_parts.append(
            f"Hồ sơ hiện tại đã có nền tảng phù hợp cho vị trí {payload.target_job}, "
            f"nổi bật ở các kỹ năng: {', '.join(overlap[:4])}."
        )
    else:
        summary_parts.append(
            f"Hồ sơ hiện tại mới ở mức khởi điểm so với vị trí {payload.target_job}, "
            "cần bổ sung thêm các kỹ năng lõi theo đúng vai trò mục tiêu."
        )

    if gaps:
        summary_parts.append(
            f"Các kỹ năng nên ưu tiên phát triển tiếp theo là: {', '.join(gaps)}."
        )

    if payload.cv_text:
        project_signals = re.search(
            r"project|dự án|portfolio", payload.cv_text, re.IGNORECASE
        )
        if not project_signals:
            summary_parts.append(
                "CV nên bổ sung thêm dự án thực tế hoặc portfolio "
                "để tăng sức thuyết phục khi ứng tuyển."
            )

    return RecommendResponse(
        match_score=match_score,
        skill_gaps=gaps,
        recommended_keyword_topics=gaps[:3] if gaps else overlap[:3],
        summary=" ".join(summary_parts).strip(),
    )


async def recommend_with_llm(
    payload: CareerAdvisorRequest,
) -> CareerAdvisorResponse:
    """
    Tư vấn nghề nghiệp thông minh sử dụng LLM kết hợp CV + Học liệu + Yêu cầu thị trường.
    """
    import json
    from services.provider import call_provider

    # Chuẩn bị thông tin học thuật
    completed_courses_str = ", ".join(payload.completed_courses)
    grade_transcript_str = "\n".join([
        f"- {g.course_title}: Điểm {g.final_score} ({g.grade_letter or 'N/A'})"
        for g in payload.grade_transcript
    ])

    prompt = f"""
Bạn là chuyên gia tư vấn hướng nghiệp AI chuyên sâu về ngành CNTT & Viễn thông.
Nhiệm vụ của bạn là phân tích CV, kỹ năng, kết quả học tập của sinh viên để đánh giá độ tương thích và xây dựng lộ trình chi tiết giúp sinh viên đạt được vị trí công việc mục tiêu.

VỊ TRÍ MỤC TIÊU: {payload.target_job}

HỒ SƠ SINH VIÊN:
- Ngành học: {payload.major or "CNTТ"} ({payload.program or "Chính quy"})
- Điểm trung bình GPA: {payload.gpa or "N/A"}/10.0
- Các môn đã học: {completed_courses_str or "Chưa có"}
- Chi tiết bảng điểm:
{grade_transcript_str or "Chưa có"}

KỸ NĂNG TRÍCH XUẤT TỪ CV:
{", ".join(payload.skills) or "Chưa có"}

KỸ NĂNG TÍCH LŨY TỪ CÁC KHÓA HỌC:
{", ".join(payload.course_skills) or "Chưa có"}

NỘI DUNG CV THÔ (TRÍCH XUẤT):
{payload.cv_text[:3000] if payload.cv_text else "Không có nội dung CV thô."}

YÊU CẦU PHÂN TÍCH:
Hãy phân tích hồ sơ trên đối chiếu với xu hướng tuyển dụng thị trường hiện nay cho vị trí "{payload.target_job}". Trả về chuỗi JSON chính xác theo cấu trúc sau:
{{
  "match_score": 75, // Số nguyên từ 0 - 100 biểu thị mức độ sẵn sàng của hồ sơ
  "market_analysis": "Phân tích xu hướng thị trường tuyển dụng hiện tại cho vị trí này, các công nghệ hot đang được săn đón.",
  "profile_assessment": "Đánh giá chi tiết về hồ sơ của sinh viên, xem họ đang ở mức nào (Fresher/Internship) và cơ hội ứng tuyển.",
  "strengths": [
    "Điểm mạnh 1 về học thuật hoặc kỹ năng trong CV",
    "Điểm mạnh 2..."
  ],
  "skill_gaps": [
    "Kỹ năng/công nghệ quan trọng mà vị trí yêu cầu nhưng sinh viên còn thiếu hoặc yếu"
  ],
  "skill_roadmap": [
    {{
      "skill": "Tên kỹ năng cần học",
      "timeline": "Thời gian (ví dụ: 1-2 tháng)",
      "resources": ["Tài liệu/Khóa học đề xuất"],
      "priority": 1 // 1 là cao nhất, tăng dần
    }}
  ],
  "alternative_careers": [
    "Nghề nghiệp thay thế/liên quan phù hợp với kỹ năng hiện tại nếu không ứng tuyển được vị trí mục tiêu"
  ],
  "recommended_keyword_topics": [
    "Tên 3-5 môn học/chủ đề cốt lõi sinh viên nên đăng ký học tiếp trên hệ thống"
  ],
  "action_plan": [
    "Bước hành động 1 (ví dụ: Cập nhật GitHub profile, tối ưu cấu trúc CV...)"
  ],
  "summary": "Lời khuyên, đúc kết ngắn gọn hướng đi tiếp theo."
}}

CHÚ Ý:
- Chỉ trả về chuỗi JSON thô, không nằm trong block markdown (như ```json), không có khoảng trắng thừa đầu/cuối.
- Các nội dung văn bản phải viết chi tiết, có chuyên môn sâu, bằng tiếng Việt.
"""

    messages = [
        {
            "role": "system",
            "content": "Bạn là chuyên gia tư vấn hướng nghiệp AI. Bạn chỉ trả về định dạng JSON thuần túy theo cấu trúc được yêu cầu.",
        },
        {"role": "user", "content": prompt},
    ]

    # Gọi provider AI
    if not (payload.api_key or "").strip():
        from fastapi import HTTPException
        raise HTTPException(status_code=502, detail="API key chưa được cấu hình. Sử dụng phân tích heuristic.")

    reply, tokens = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    )

    if not reply:
        logger.error("Career advisor AI response is empty")
        return CareerAdvisorResponse(summary="Không thể tạo tư vấn nghề nghiệp lúc này.")

    try:
        # Làm sạch response đề phòng LLM tự ý thêm markdown code block
        cleaned_reply = reply.strip()
        if cleaned_reply.startswith("```json"):
            cleaned_reply = cleaned_reply[7:]
        if cleaned_reply.endswith("```"):
            cleaned_reply = cleaned_reply[:-3]
        cleaned_reply = cleaned_reply.strip()

        data = json.loads(cleaned_reply)
        return CareerAdvisorResponse(**data)

    except Exception as e:
        logger.error(f"Failed to parse Career Advisor JSON response: {e}. Raw reply: {reply}")
        # Fallback return
        return CareerAdvisorResponse(
            match_score=50,
            summary="Đã xảy ra lỗi khi phân tích hồ sơ nghề nghiệp: " + str(e),
            profile_assessment="Hệ thống đang gặp sự cố khi trích xuất dữ liệu hướng nghiệp tự động từ LLM."
        )

