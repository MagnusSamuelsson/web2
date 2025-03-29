import { JSX, useContext, useEffect } from "react";
import { AuthContext } from "../contexts/AuthContext";
import { useNavigate } from "react-router-dom";

interface AuthRouterProps {
  AuthElement: JSX.Element;
  NoAuthElement?: JSX.Element;
}
const AuthRouter: React.FC<AuthRouterProps> = ({ AuthElement, NoAuthElement }) => {
  const auth = useContext(AuthContext);
  const navigate = useNavigate();

  useEffect(() => {
    if (!auth?.authenticated && !NoAuthElement) {
      navigate("/");
    }
  }, [auth?.authenticated, NoAuthElement, navigate]);

  if (!auth) return null;
  return (
    <>
      {auth.authenticated && AuthElement}
      {!auth.authenticated && NoAuthElement}
    </>
  )
};

export default AuthRouter;
