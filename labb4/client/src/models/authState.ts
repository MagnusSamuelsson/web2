import { User } from "../models/user";

export default interface AuthState {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
}