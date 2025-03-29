import { create } from 'zustand'

type AuthModalState = {
  isOpen: boolean
  open: () => void
  close: () => void
}

export const useAuthModal = create<AuthModalState>((set) => ({
  isOpen: false,
  open: () => set({ isOpen: true }),
  close: () => set({ isOpen: false }),
}))