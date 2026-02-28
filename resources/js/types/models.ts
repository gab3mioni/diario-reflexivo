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
    } | null;
};
