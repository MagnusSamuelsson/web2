
import { QueryClient } from "@tanstack/react-query";


const queryClientInst = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 10,
      retry: false,
    },
  },
});

export const queryClient = queryClientInst;