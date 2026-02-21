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