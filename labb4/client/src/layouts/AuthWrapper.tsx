import { ReactNode, useMemo } from "react";
import { AuthContext } from "../contexts/AuthContext";
import { useApiAuth } from "../hooks/useApiAuth";
import { useApiUser } from "../hooks/useApiUser";
import { LoadingPage } from "../pages/LoadingPage/LoadingPage";

interface AuthWrapperProps {
  children: ReactNode;
}

const AuthWrapper: React.FC<AuthWrapperProps> = ({ children }) => {
  const token = useApiAuth();
  const user = useApiUser();

  const accessToken = token.data?.access_token ?? null;
  const userData = user.data ?? null;

  const contextValue = useMemo(() => ({
    authenticated: accessToken !== null,
    user: userData,
    token: accessToken
  }), [userData, accessToken]);

  if (token.isLoading || user.isLoading) {
    return <LoadingPage />;
  }

  return (
    <AuthContext.Provider value={contextValue}>
      {children}
    </AuthContext.Provider>
  );
};

export default AuthWrapper;
