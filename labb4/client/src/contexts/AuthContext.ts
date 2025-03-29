import { createContext } from "react";
import { User } from "../models/user";

export interface AuthContextType {
  authenticated: boolean;
  user: User | null;
  token: string | null;
}

export const AuthContext = createContext<AuthContextType | null>(null);