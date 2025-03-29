import {User} from './user';

export type Post = {
    id: number;
    user_id: number;
    number_of_likes: number;
    number_of_comments: number;
    content: string;
    images: string[];
    created_at: string;
    updated_at: string;
    liked_by_current_user: boolean;
    user: User;
}

export type Posts = {
    posts: Post[];
}