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


class AIResponse(BaseModel):
    """Response wrapper chuẩn cho các endpoint trả về kết quả AI."""
    reply: str
    tokens_used: TokenUsage = Field(default_factory=lambda: {**EMPTY_TOKENS})


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


class ChatContext(BaseModel):
    """Context đầy đủ cho chatbot."""
    courses: list[CourseContext] = Field(default_factory=list)
    categories: list[CategoryContext] = Field(default_factory=list)
    current_course: CourseContext | None = None


class ChatRequest(BaseModel):
    """Request body cho endpoint /chat."""
    message: str
    user_id: int | None = None
    course_id: int | None = None
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None
    role: str | None = None
    context: ChatContext | None = None
    history: list[dict[str, str]] = Field(default_factory=list)


# =============================================================================
# Career Advisor
# =============================================================================

class ParseCVRequest(BaseModel):
    """Request body cho endpoint /parse-cv."""
    file_path: str
    user_id: int
    provider: str | None = "chatgpt"
    model: str | None = "gpt-4o-mini"
    api_key: str | None = None


class ParseCVResponse(BaseModel):
    """Response cho endpoint /parse-cv."""
    text: str = ""
    skills: list[str] = Field(default_factory=list)


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


# =============================================================================
# RAG (Retrieval-Augmented Generation) — Phase 1-2
# =============================================================================

class RagIngestRequest(BaseModel):
    """Request ingest tài liệu từ URL."""
    file_url: str
    subject_name: str | None = None
    course_id: int | None = None
    collection_name: str | None = None


class RagIngestResponse(BaseModel):
    """Response sau khi ingest tài liệu."""
    success: bool
    chunks_added: int = 0
    collection_name: str = ""
    message: str = ""


class RagQueryRequest(BaseModel):
    """Request truy vấn tài liệu liên quan."""
    question: str
    course_id: int | None = None
    subject_name: str | None = None
    collection_name: str | None = None
    top_k: int = 5


class RagSourceItem(BaseModel):
    """Một nguồn tài liệu trả về từ RAG."""
    content: str
    source_file: str = ""
    subject_name: str = ""
    relevance_score: float = 0


class RagQueryResponse(BaseModel):
    """Response truy vấn RAG."""
    chunks_found: int = 0
    sources: list[dict] = Field(default_factory=list)
    context_text: str = ""


class RagCollectionInfo(BaseModel):
    """Thông tin collection trong ChromaDB."""
    name: str
    document_count: int = 0


# ChatRequest mở rộng với RAG context
class ChatSource(BaseModel):
    """Nguồn tài liệu được trích dẫn trong câu trả lời."""
    source_file: str = ""
    subject_name: str = ""
    relevance_score: float = 0
    content_preview: str = ""


class ChatResponseWithSources(BaseModel):
    """Chat response bao gồm cả nguồn tài liệu từ RAG."""
    reply: str
    sources: list[ChatSource] = Field(default_factory=list)
    has_rag_context: bool = False
    tokens_used: TokenUsage = Field(default_factory=lambda: {**EMPTY_TOKENS})


# =============================================================================
# AI Learning Advisor — Phase 3
# =============================================================================

class GradeRecord(BaseModel):
    """Bản ghi điểm một môn học."""
    course_id: int
    course_title: str
    final_score: float | None = None
    grade_letter: str | None = None  # A, B, C, D, F
    credits: int = 3
    term_number: int | None = None


class QuizPerformance(BaseModel):
    """Kết quả quiz của sinh viên."""
    quiz_title: str
    score: float
    passed: bool
    attempts: int = 1


class CurriculumGap(BaseModel):
    """Môn học bắt buộc chưa hoàn thành."""
    course_id: int
    course_title: str
    credits: int = 3
    term_number: int | None = None
    is_required: bool = True


class LearningAdvisorRequest(BaseModel):
    """Request phân tích học tập toàn diện."""
    user_id: int
    student_name: str | None = None
    major: str | None = None
    program: str | None = None
    current_term: int | None = None

    # Tiến độ khóa học marketplace
    enrolled_courses: list[StudentProgress] = Field(default_factory=list)

    # Bảng điểm học thuật
    grade_transcript: list[GradeRecord] = Field(default_factory=list)

    # Kết quả quiz
    quiz_performance: list[QuizPerformance] = Field(default_factory=list)

    # Môn chưa học trong chương trình đào tạo
    curriculum_gaps: list[CurriculumGap] = Field(default_factory=list)

    # Thống kê học tập
    gpa: float | None = None
    total_completed_courses: int = 0
    total_credits_earned: int = 0

    # AI provider
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class StudyPlanItem(BaseModel):
    """Một mục trong kế hoạch học tập."""
    title: str
    description: str = ""
    priority: str = "medium"  # high, medium, low
    type: str = "course"      # course, skill, review


class LearningAdvisorResponse(BaseModel):
    """Response khuyến nghị học tập từ AI."""
    overall_assessment: str = ""
    strengths: list[str] = Field(default_factory=list)
    weaknesses: list[str] = Field(default_factory=list)
    recommended_courses: list[str] = Field(default_factory=list)
    skills_to_develop: list[str] = Field(default_factory=list)
    study_plan: list[StudyPlanItem] = Field(default_factory=list)
    next_term_suggestions: list[str] = Field(default_factory=list)
    motivational_message: str = ""


# =============================================================================
# AI Career Advisor (LLM-powered) — Phase 4
# =============================================================================

class CareerAdvisorRequest(BaseModel):
    """Request phân tích nghề nghiệp đầy đủ."""
    # Thông tin cơ bản
    user_id: int
    target_job: str

    # CV
    skills: list[str] = Field(default_factory=list)
    cv_text: str | None = None

    # Thông tin học thuật (mới)
    major: str | None = None
    program: str | None = None
    gpa: float | None = None
    completed_courses: list[str] = Field(default_factory=list)  # Tên môn đã hoàn thành
    grade_transcript: list[GradeRecord] = Field(default_factory=list)

    # Kỹ năng từ khóa học đã học
    course_skills: list[str] = Field(default_factory=list)

    # AI provider
    provider: str | None = "chatgpt"
    model: str | None = None
    api_key: str | None = None


class SkillRoadmapStep(BaseModel):
    """Một bước trong lộ trình kỹ năng."""
    skill: str
    timeline: str = "1-3 tháng"
    resources: list[str] = Field(default_factory=list)
    priority: int = 1


class CareerAdvisorResponse(BaseModel):
    """Response từ AI Career Advisor LLM-powered."""
    match_score: int = 0
    market_analysis: str = ""       # Phân tích yêu cầu thị trường
    profile_assessment: str = ""    # Đánh giá hồ sơ hiện tại
    strengths: list[str] = Field(default_factory=list)
    skill_gaps: list[str] = Field(default_factory=list)
    skill_roadmap: list[SkillRoadmapStep] = Field(default_factory=list)
    alternative_careers: list[str] = Field(default_factory=list)  # Nghề nghiệp thay thế
    recommended_keyword_topics: list[str] = Field(default_factory=list)
    action_plan: list[str] = Field(default_factory=list)
    summary: str = ""
