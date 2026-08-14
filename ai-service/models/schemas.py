"""
Pydantic schemas cho toàn bộ AI Service.
Tất cả request/response models tập trung tại đây.
"""

from __future__ import annotations

from pydantic import BaseModel, Field


# =============================================================================
# Shared / Common
# =============================================================================

TokenUsage = dict[str, int]  # {"prompt": x, "completion": y, "total": z}


EMPTY_TOKENS: TokenUsage = {"prompt": 0, "completion": 0, "total": 0}


class RagSource(BaseModel):
    """Nguồn giáo trình được retrieve cho câu trả lời."""
    source: str
    subject: str | None = None
    score: float | None = None


class AIResponse(BaseModel):
    """Response wrapper chuẩn cho các endpoint trả về kết quả AI."""
    reply: str
    tokens_used: TokenUsage = Field(default_factory=lambda: {**EMPTY_TOKENS})
    rag_used: bool = False
    sources: list[RagSource] = Field(default_factory=list)


# =============================================================================
# Chat
# =============================================================================

class CourseContext(BaseModel):
    """Thông tin 1 khóa học trong context."""
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
    """Danh mục con."""
    id: int
    name: str


class CategoryContext(BaseModel):
    """Danh mục cha kèm danh mục con."""
    id: int
    name: str
    children: list[CategoryItem] = Field(default_factory=list)


class CareerPathContext(BaseModel):
    """Lộ trình nghề trong context guest/chat."""
    id: int
    title: str
    target_role: str | None = None
    description: str | None = None


class ChatContext(BaseModel):
    """Context đầy đủ cho chatbot."""
    courses: list[CourseContext] = Field(default_factory=list)
    categories: list[CategoryContext] = Field(default_factory=list)
    career_paths: list[CareerPathContext] = Field(default_factory=list)
    current_course: CourseContext | None = None
    guest_mode: bool | None = None
    hint: str | None = None


class ChatRequest(BaseModel):
    """Request body cho endpoint /chat."""
    message: str
    user_id: int | None = None
    course_id: int | None = None
    provider: str | None = "gemini"
    model: str | None = "gemini-2.5-flash"
    api_key: str | None = None
    role: str | None = None
    context: ChatContext | None = None
    history: list[dict[str, str]] = Field(default_factory=list)
    # RAG: hỏi đáp theo giáo trình (mặc định bật cho student)
    use_rag: bool | None = None
    rag_top_k: int = Field(default=5, ge=1, le=12)
    # global = mọi giáo trình; course = chỉ môn của khóa đang học
    rag_scope: str | None = None
    subject_hint: str | None = None


# =============================================================================
# Career Advisor
# =============================================================================

class ParseCVRequest(BaseModel):
    """Request body cho endpoint /parse-cv."""
    file_path: str | None = None
    file_base64: str | None = None
    file_name: str | None = None
    user_id: int = 0
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None


class ParseCVResponse(BaseModel):
    """Response cho endpoint /parse-cv."""
    text: str = ""
    skills: list[str] = Field(default_factory=list)
    method: str = "none"
    ocr_used: bool = False


class RecommendRequest(BaseModel):
    """Request body cho endpoint /recommend."""
    skills: list[str]
    target_job: str
    cv_text: str | None = None
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None


class RecommendResponse(BaseModel):
    """Response cho endpoint /recommend."""
    match_score: int = 0
    skill_gaps: list[str] = Field(default_factory=list)
    recommended_keyword_topics: list[str] = Field(default_factory=list)
    summary: str = ""


class EvaluateCVRequest(BaseModel):
    """Đánh giá CV theo góc nhìn nhà tuyển dụng."""
    skills: list[str] = Field(default_factory=list)
    target_job: str
    cv_text: str | None = None
    expected_salary: int | None = None
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None


class EvaluateCVResponse(BaseModel):
    """Bài đánh giá CV chi tiết."""
    match_score: int = 0
    verdict: str = ""
    overview: str = ""
    strengths: list[str] = Field(default_factory=list)
    weaknesses: list[str] = Field(default_factory=list)
    improvements: list[str] = Field(default_factory=list)
    missing_items: list[str] = Field(default_factory=list)
    skill_gaps: list[str] = Field(default_factory=list)
    interview_focus: list[str] = Field(default_factory=list)
    recommended_keyword_topics: list[str] = Field(default_factory=list)
    salary_comment: str | None = None


# =============================================================================
# Content Generator (Phase 2)
# =============================================================================

class GenerateCourseTitleRequest(BaseModel):
    """Request sinh tiêu đề khóa học."""
    category: str
    keywords: list[str] = Field(default_factory=list)
    target_audience: str | None = None
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class GenerateCourseTitleResponse(BaseModel):
    """Response danh sách tiêu đề gợi ý."""
    titles: list[str] = Field(default_factory=list)


class GenerateLessonDescriptionRequest(BaseModel):
    """Request sinh mô tả bài học."""
    course_title: str
    lesson_title: str
    section_context: str | None = None
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class GenerateLessonDescriptionResponse(BaseModel):
    """Response mô tả bài học."""
    description: str = ""


class QuestionOption(BaseModel):
    """Đáp án cho 1 câu hỏi trắc nghiệm."""
    text: str
    is_correct: bool = False


class GeneratedQuestion(BaseModel):
    """1 câu hỏi được AI sinh ra."""
    question: str
    type: str = "single_choice"  # single_choice, multiple_choice, true_false, fill_blank
    difficulty: str = "medium"   # easy, medium, hard
    options: list[QuestionOption] = Field(default_factory=list)
    explanation: str | None = None


class GenerateQuizRequest(BaseModel):
    """Request sinh câu hỏi quiz."""
    lesson_content: str
    lesson_title: str | None = None
    difficulty: str = "medium"
    count: int = 5
    question_types: list[str] = Field(default_factory=lambda: ["single_choice"])
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class GenerateQuizResponse(BaseModel):
    """Response danh sách câu hỏi."""
    questions: list[GeneratedQuestion] = Field(default_factory=list)


class GenerateExamRequest(BaseModel):
    """Request sinh đề thi."""
    course_title: str
    topics: list[str] = Field(default_factory=list)
    difficulty_distribution: dict[str, int] = Field(
        default_factory=lambda: {"easy": 30, "medium": 50, "hard": 20}
    )
    count: int = 20
    question_types: list[str] = Field(default_factory=lambda: ["single_choice", "multiple_choice", "true_false"])
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class GenerateExamResponse(BaseModel):
    """Response đề thi."""
    questions: list[GeneratedQuestion] = Field(default_factory=list)
    exam_title: str | None = None


# =============================================================================
# Exam Analytics (Phase 3)
# =============================================================================

class QuestionStat(BaseModel):
    """Thống kê 1 câu hỏi trong kỳ thi."""
    question_id: int
    question_text: str
    correct_count: int = 0
    incorrect_count: int = 0
    skip_count: int = 0
    avg_time_seconds: float | None = None


class ExamAnalyzeRequest(BaseModel):
    """Request phân tích kỳ thi."""
    exam_title: str
    total_students: int
    avg_score: float | None = None
    score_distribution: list[int] = Field(default_factory=list)
    questions_stats: list[QuestionStat] = Field(default_factory=list)
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class ExamAnalyzeResponse(BaseModel):
    """Response phân tích kỳ thi."""
    insights: str = ""
    difficult_questions: list[dict] = Field(default_factory=list)
    common_mistakes: list[str] = Field(default_factory=list)
    recommendations_for_instructor: list[str] = Field(default_factory=list)
    recommendations_for_students: list[str] = Field(default_factory=list)


# =============================================================================
# Smart Tutoring (Phase 4)
# =============================================================================

class StudentProgress(BaseModel):
    """Tiến độ học tập của 1 khóa học."""
    course_id: int
    course_title: str
    progress_percent: float = 0
    quiz_avg_score: float | None = None
    last_accessed: str | None = None


class TutoringRequest(BaseModel):
    """Request gợi ý học tập."""
    user_id: int
    enrolled_courses: list[StudentProgress] = Field(default_factory=list)
    quiz_scores: list[dict] = Field(default_factory=list)
    study_pattern: dict | None = None
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class TutoringResponse(BaseModel):
    """Response gợi ý học tập."""
    review_lessons: list[str] = Field(default_factory=list)
    next_courses: list[str] = Field(default_factory=list)
    weak_skills: list[str] = Field(default_factory=list)
    study_tips: list[str] = Field(default_factory=list)
    summary: str = ""


class StudyAdvisorRequest(BaseModel):
    """
    Request tư vấn học tập — dùng số liệu CTĐT/GPA/điểm yếu thật đã tính rule-based
    (từ CurriculumEvaluationService bên Laravel), khác với TutoringRequest ở trên
    (vốn chỉ có % tiến độ chung, dùng cho widget mẹo học bài).
    """
    user_id: int
    academic_summary: dict = Field(default_factory=dict)
    strengths: list[dict] = Field(default_factory=list)
    weaknesses: list[dict] = Field(default_factory=list)
    by_term: list[dict] = Field(default_factory=list)
    target_roles: list[str] = Field(default_factory=list)
    top_skills: list[str] = Field(default_factory=list)
    quiz_scores: list[dict] = Field(default_factory=list)
    rule_based_narrative: str = ""
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class StudyAdvisorResponse(BaseModel):
    """Response tư vấn học tập."""
    narrative: str = ""
    study_tips: list[str] = Field(default_factory=list)
    focus_courses: list[str] = Field(default_factory=list)
