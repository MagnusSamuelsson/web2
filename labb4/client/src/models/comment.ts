import { User } from './user';

export type Comment = {
    id: number;
    post_id: number;
    user_id: number;
    content: string;
    created_at: string;
    updated_at: string;
    reply_comment_id?: number | null;
    user: User;
    answers: Comment[];
}