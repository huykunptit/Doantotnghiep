"""
Career Service — phân tích CV và gợi ý nghề nghiệp.
"""

from __future__ import annotations

import re

from models.schemas import (
    ParseCVRequest,
    ParseCVResponse,
    RecommendRequest,
    RecommendResponse,
)


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
