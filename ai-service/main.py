from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
import json
import re
from urllib import error, request

app = FastAPI(title="LMS AI Service")


class CourseContext(BaseModel):
    id: int
    title: str
    description: str | None = None
    price: int | float | None = 0
    category: str | None = None
    instructor: str | None = None
    lessons_count: int | None = 0
    enrollments_count: int | None = 0
    reviews_count: int | None = 0
    rating: float | None = 0


class CategoryItem(BaseModel):
    id: int
    name: str


class CategoryContext(BaseModel):
    id: int
    name: str
    children: list[CategoryItem] = Field(default_factory=list)


class ChatContext(BaseModel):
    courses: list[CourseContext] = Field(default_factory=list)
    categories: list[CategoryContext] = Field(default_factory=list)
    current_course: CourseContext | None = None


class ChatRequest(BaseModel):
    message: str
    user_id: int | None = None
    course_id: int | None = None
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None
    context: ChatContext | None = None


class ParseCVRequest(BaseModel):
    file_path: str
    user_id: int
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None


class RecommendRequest(BaseModel):
    skills: list[str]
    target_job: str
    cv_text: str | None = None
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None


def format_price(value: int | float | None) -> str:
    if not value or value <= 0:
        return "Miễn phí"
    amount = int(round(float(value)))
    return f"{amount:,}".replace(",", ".") + " đ"


def format_course_line(course: CourseContext) -> str:
    parts = [
        f"- {course.title}",
        f"chuyên mục {course.category or 'không rõ'}",
        f"giảng viên {course.instructor or 'EduPress'}",
        f"học phí {format_price(course.price)}",
        f"{course.lessons_count or 0} bài học",
    ]
    if course.rating:
        parts.append(f"đánh giá {course.rating:.1f}/5")
    return ", ".join(parts)


def build_context_summary(context: ChatContext) -> str:
    lines: list[str] = []

    if context.current_course:
        lines.append("KHOA_HOC_DANG_XEM:")
        lines.append(format_course_line(context.current_course))

    if context.categories:
        lines.append("DANH_MUC:")
        for category in context.categories[:8]:
            child_names = ", ".join(child.name for child in category.children[:4])
            label = f"- {category.name}"
            if child_names:
                label += f" | nhánh con: {child_names}"
            lines.append(label)

    if context.courses:
        lines.append("KHOA_HOC_NOI_BAT:")
        for course in context.courses[:12]:
            lines.append(format_course_line(course))

    return "\n".join(lines).strip()


def build_ai_messages(payload: ChatRequest) -> tuple[str, str]:
    context = payload.context or ChatContext()
    context_summary = build_context_summary(context)

    system_prompt = (
        "Bạn là trợ lý AI của EduPress. "
        "Nhiệm vụ là tư vấn khóa học, giải thích cách dùng hệ thống, gợi ý lộ trình học và trả lời ngắn gọn bằng tiếng Việt. "
        "Ưu tiên dùng dữ liệu thật trong phần context. Không bịa khóa học không có trong context. "
        "Nếu người dùng hỏi chung chung, hãy hỏi lại ngắn gọn hoặc gợi ý 3-4 hướng cụ thể. "
        "Nếu có thể, hãy đề xuất tối đa 4 khóa học phù hợp và nêu ngắn lý do chọn."
    )

    user_prompt = (
        f"Câu hỏi người dùng: {payload.message.strip()}\n\n"
        f"Context hệ thống:\n{context_summary or 'Không có dữ liệu ngữ cảnh.'}"
    )

    return system_prompt, user_prompt


def call_openai_chat(payload: ChatRequest) -> str | None:
    system_prompt, user_prompt = build_ai_messages(payload)
    body = {
        "model": payload.model or "gpt-4o-mini",
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "temperature": 0.4,
    }

    req = request.Request(
        "https://api.openai.com/v1/chat/completions",
        data=json.dumps(body).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {payload.api_key}",
        },
        method="POST",
    )

    with request.urlopen(req, timeout=20) as response:
        data = json.loads(response.read().decode("utf-8"))
        return data["choices"][0]["message"]["content"].strip()


def call_gemini_chat(payload: ChatRequest) -> str | None:
    system_prompt, user_prompt = build_ai_messages(payload)
    model = payload.model or "gemini-2.5-flash"
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={payload.api_key}"
    body = {
        "system_instruction": {"parts": [{"text": system_prompt}]},
        "contents": [
            {"role": "user", "parts": [{"text": user_prompt}]},
        ],
        "generationConfig": {"temperature": 0.4},
    }

    req = request.Request(
        url,
        data=json.dumps(body).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    with request.urlopen(req, timeout=20) as response:
        data = json.loads(response.read().decode("utf-8"))
        candidates = data.get("candidates") or []
        if not candidates:
            return None
        parts = candidates[0].get("content", {}).get("parts", [])
        text = "\n".join(part.get("text", "").strip() for part in parts if part.get("text"))
        return text.strip() or None


def call_provider_chat(payload: ChatRequest) -> str | None:
    provider = (payload.provider or "chatgpt").strip().lower()

    try:
        if provider == "gemini":
            return call_gemini_chat(payload)
        if provider == "chatgpt":
            return call_openai_chat(payload)
        raise HTTPException(status_code=400, detail=f"Provider không hỗ trợ: {provider}")
    except error.HTTPError as exc:
        try:
            details = exc.read().decode("utf-8")
        except Exception:
            details = str(exc)
        raise HTTPException(status_code=502, detail=f"{provider} http error: {details}") from exc
    except HTTPException:
        raise
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"{provider} request failed: {exc}") from exc


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/chat")
def chat(payload: ChatRequest) -> dict[str, str]:
    if not payload.api_key:
        raise HTTPException(status_code=400, detail="Thiếu API key cho provider AI. Vui lòng cấu hình API key trong phần Cài đặt.")

    provider = (payload.provider or "chatgpt").strip().lower()
    if provider not in {"chatgpt", "gemini"}:
        raise HTTPException(status_code=400, detail=f"Provider không hỗ trợ: {provider}")

    reply = call_provider_chat(payload)
    if not reply:
        raise HTTPException(status_code=502, detail="Provider AI không trả về nội dung.")

    return {"reply": reply}


@app.post("/parse-cv")
def parse_cv(payload: ParseCVRequest) -> dict:
    return {
        "text": "",
        "skills": []
    }


@app.post("/recommend")
def recommend(payload: RecommendRequest) -> dict:
    normalized_job = (payload.target_job or "").strip().lower()
    skill_lookup = {skill.lower(): skill for skill in payload.skills}

    role_skill_map = {
        "laravel": ["PHP", "Laravel", "MySQL", "REST API", "Git", "Docker"],
        "php": ["PHP", "Laravel", "MySQL", "REST API", "Git", "Docker"],
        "backend": ["PHP", "Laravel", "MySQL", "REST API", "Testing", "Docker"],
        "frontend": ["JavaScript", "TypeScript", "Vue.js", "Nuxt", "HTML/CSS", "Git"],
        "full stack": ["PHP", "Laravel", "JavaScript", "Vue.js", "MySQL", "Docker"],
        "devops": ["Docker", "Linux", "AWS", "Git", "Testing"],
        "data": ["Python", "SQL", "Testing"],
    }

    market_skills = ["Git", "REST API", "Docker"]
    for keyword, skills in role_skill_map.items():
        if keyword in normalized_job:
            market_skills = skills
            break

    current_skills_lower = set(skill_lookup.keys())
    gaps = [skill for skill in market_skills if skill.lower() not in current_skills_lower]
    overlap = [skill for skill in market_skills if skill.lower() in current_skills_lower]

    total = max(len(market_skills), 1)
    match_score = int(round((len(overlap) / total) * 100))
    match_score = max(35, min(match_score, 95))

    summary_parts = []
    if overlap:
        summary_parts.append(
            f"Hồ sơ hiện tại đã có nền tảng phù hợp cho vị trí {payload.target_job}, nổi bật ở các kỹ năng: {', '.join(overlap[:4])}."
        )
    else:
        summary_parts.append(
            f"Hồ sơ hiện tại mới ở mức khởi điểm so với vị trí {payload.target_job}, cần bổ sung thêm các kỹ năng lõi theo đúng vai trò mục tiêu."
        )

    if gaps:
        summary_parts.append(
            f"Các kỹ năng nên ưu tiên phát triển tiếp theo là: {', '.join(gaps)}."
        )

    if payload.cv_text:
        project_signals = re.search(r"project|dự án|portfolio", payload.cv_text, re.IGNORECASE)
        if not project_signals:
            summary_parts.append("CV nên bổ sung thêm dự án thực tế hoặc portfolio để tăng sức thuyết phục khi ứng tuyển.")

    return {
        "match_score": match_score,
        "skill_gaps": gaps,
        "recommended_keyword_topics": gaps[:3] if gaps else overlap[:3],
        "summary": " ".join(summary_parts).strip(),
    }
