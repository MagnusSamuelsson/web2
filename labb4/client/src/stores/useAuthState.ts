import { create } from 'zustand'
import { User } from '../models/user'

type AuthState = {
    isAuthenticated: boolean
    isAuthChecked: boolean
    setAuthenticated: (value: boolean) => void
    setAuthChecked: (value: boolean) => void
    user: User | null,
    setUser: (value: User | null) => void
}

export const useAuthState = create<AuthState>((set) => ({
    isAuthenticated: false,
    isAuthChecked: false,
    setAuthenticated: (value) => set({ isAuthenticated: value }),
    setAuthChecked: (value) => set({ isAuthChecked: value }),
    user: null,
    setUser: (value) => set({ user: value })
}))