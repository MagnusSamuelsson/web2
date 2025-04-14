import { useRef, useState } from "react";
import styles from "./ModalLoginForm.module.css";
import { LoginModel } from "../../models/loginModel";
import { useLogin } from "../../hooks/useLogin";


interface LoginFormProps {
  onSwitch: () => void;
  onClose: () => void;
}

const ModalLoginForm: React.FC<LoginFormProps> = ({ onSwitch, onClose }) => {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const login = useLogin();
  const [error, setError] = useState<string | null>(null);
  const userNameInput = useRef<HTMLInputElement>(null);
  const passwordInput = useRef<HTMLInputElement>(null);

  const setValidationState = (element: React.RefObject<HTMLInputElement | null>, isValid: boolean) => {
    if (!element.current) return;

    element.current.classList.toggle("errorBorder", !isValid);
    element.current.classList.toggle("validBorder", isValid);
    element.current.setAttribute("aria-invalid", isValid ? "false" : "true");
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const loginModel: LoginModel = {
      username,
      password
    };

    if (!loginModel.username && !loginModel.password) {
      setError("Both username and password are required.");
      setValidationState(userNameInput, false);
      setValidationState(passwordInput, false);
      return
    }
    if (!loginModel.username) {
      setError("Username is required.");
      setValidationState(userNameInput, false);
      return
    }
    if (!loginModel.password) {
      setError("Password is required.");
      setValidationState(passwordInput, false);
      return
    }

    try {
      await login.mutateAsync({ username: loginModel.username, password: loginModel.password });
      onClose();
    } catch (error) {
      setError("Login failed. Please check your credentials and try again.");
      setValidationState(userNameInput, false);
      setValidationState(passwordInput, false);
      console.error("Något gick snett", error);
    }
  }

  const changeUsername = (value: string) => {
    setUsername(value);
    setError(null);
    if (value.length > 1) {
      setValidationState(userNameInput, true);
      setValidationState(passwordInput, true);
    }
  }

  const changePassword = (value: string) => {
    setPassword(value);
    setError(null);
    if (value.length > 1) {
      setValidationState(userNameInput, true);
      setValidationState(passwordInput, true);
    }
  }

  return (
    <form className={styles.form} onSubmit={handleSubmit}>
      {error && <p
        aria-label="Error message"
        aria-description="Error message"
        role="alert"
        aria-live="assertive"
        className={styles.errorMessage}
      >
        {error}
      </p>}
      <input
        aria-label="Username"
        autoFocus={true}
        type="text"
        placeholder="Username"
        className={styles.input}
        value={username}
        ref={userNameInput}
        onChange={(e) => changeUsername(e.target.value)}
        autoComplete="username"
        aria-required="true"

      />
      <input
        aria-label="Password"
        type="password"
        placeholder="Password"
        className={styles.input}
        value={password}
        ref={passwordInput}
        onChange={(e) => changePassword(e.target.value)}
        autoComplete="current-password"
        aria-required="true"
      />

      <button type="submit" className={styles.button}>
        Log in
      </button>
      <p className={styles.switchText}>
        No account? <button
          aria-description="Switch to register"
          tabIndex={0}
          className={styles.switchLink}
          onClick={onSwitch}>
          Register
        </button>
      </p>
    </form>
  );
};

export default ModalLoginForm;
