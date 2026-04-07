import type { User } from './auth';

export type Course = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type Subject = {
    id: number;
    name: string;
    slug: string;
    course_id: number;
    teacher_id: number;
    is_active: boolean;
    course?: Course;
    teacher?: User;
    students?: User[];
    created_at: string;
    updated_at: string;
};

export type Student = {
    id: number;
    name: string;
    email: string;
    subjects: Subject[];
};

export type Lesson = {
    id: number;
    title: string;
    description: string | null;
    scheduled_at: string;
    is_active: boolean;
    is_available: boolean;
    subject: {
        id: number;
        name: string;
    };
    responses_count?: number;
    students_count?: number;
};

export type LessonResponse = {
    id: number;
    content: string;
    submitted_at: string | null;
};

export type StudentLesson = {
    id: number;
    title: string;
    description: string | null;
    scheduled_at: string;
    is_available: boolean;
    subject: {
        id: number;
        name: string;
    };
    response: LessonResponse | null;
};

export type StudentLessonForTeacher = {
    id: number;
    title: string;
    description: string | null;
    scheduled_at: string;
    is_available: boolean;
    subject: {
        id: number;
        name: string;
    };
    response: {
        id: number;
        content: string;
        submitted_at: string | null;
    } | null;
};

export type LessonStudentDetail = {
    id: number;
    name: string;
    email: string;
    responded: boolean;
    response: {
        id: number;
        content: string;
        submitted_at: string | null;
        chat_messages?: ChatMessage[];
        latest_analysis_status?: DiaryAnalysis['status'] | null;
    } | null;
};

export type ChatMessage = {
    id: number;
    node_id: string | null;
    role: 'bot' | 'student';
    content: string;
    created_at: string;
};

export type QuestionScriptNode = {
    id: string;
    type: 'start' | 'question' | 'end';
    position: { x: number; y: number };
    data: {
        message: string;
    };
};

export type QuestionScriptEdge = {
    id: string;
    source: string;
    target: string;
};

export type QuestionScript = {
    id: number;
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
};

export type DiaryAnalysisResult = {
    resumo: string;
    indicadores: {
        compreensao: number;
        engajamento: number;
        pensamento_critico: number;
        clareza_expressao: number;
        reflexao_pessoal: number;
    };
    pontos_fortes: string[];
    pontos_atencao: string[];
    sugestoes_acao: string[];
};

export type DiaryAnalysis = {
    id: number;
    lesson_response_id: number;
    status: 'pending' | 'completed' | 'failed' | 'approved' | 'rejected';
    result: DiaryAnalysisResult | null;
    error_message: string | null;
    teacher_notes: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    prompt_version: number;
    provider_name: string;
    model_name: string;
    created_at: string;
};
