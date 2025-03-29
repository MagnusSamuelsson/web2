import { useRef, useState } from "react";
import styles from "./ModalLoginForm.module.css";
import { LoginModel } from "../../models/loginModel";
import api from "../../services/apiClient";
import { useLogin } from "../../hooks/useLogin";

interface RegisterFormProps {
  onSwitch: () => void;
  onClose: () => void;
}

const ModalRegisterForm: React.FC<RegisterFormProps> = ({ onSwitch, onClose }) => {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [passwordRepeat, setPasswordRepeat] = useState("");
  const [error, setError] = useState<string | null>(null);
  const userNameInput = useRef<HTMLInputElement>(null);
  const passwordInput = useRef<HTMLInputElement>(null);
  const repeatPasswordInput = useRef<HTMLInputElement>(null);

  const { mutate: login } = useLogin();

  const validatePassword = (password: string): boolean => {
    return password.length > 5;

  }

  const validateUsername = (username: string): boolean => {
    return username.length > 4;
  }

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
    let valid = true;

    if (!loginModel.username && !loginModel.password) {
      if (valid) setError("Both username and password are required.");
      setValidationState(userNameInput, false);
      setValidationState(passwordInput, false);
      valid = false;
    }

    if (!validateUsername(loginModel.username)) {
      if (valid) setError("Username must be at least 5 characters long.");
      valid = false;
    }

    if (!validatePassword(loginModel.password)) {
      if (valid) setError("Password must be at least 6 characters long.");
      valid = false;
    }
    if (loginModel.password !== passwordRepeat) {
      if (valid) setError("Passwords do not match.");
      setValidationState(repeatPasswordInput, false);
      setValidationState(passwordInput, false);
      valid = false;
    }

    if (!valid) return;
    try {
      await api.post("/api/auth/register", loginModel);
      login({ username: loginModel.username, password: loginModel.password });
      onClose();
    } catch (error) {
      console.error("Något gick snett", error);
    }
  }

  const changeUsername = (value: string) => {
    setUsername(value);
    setError(null);
    if (validateUsername(value)) {
      setValidationState(userNameInput, true);
    } else {
      setValidationState(userNameInput, false);
      return
    }
  }
  const changePassword = (value: string) => {
    setPassword(value);
    setError(null);
    if (validatePassword(value)) {
      setValidationState(passwordInput, true);
      return;
    }
    setValidationState(passwordInput, false);
  }

  const changeRepeatPassword = (value: string) => {
    setPasswordRepeat(value);
    if (value !== password) {
      setValidationState(repeatPasswordInput, false);
      return
    }

    setValidationState(repeatPasswordInput, true);

    if (validatePassword(password)) {
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
      </p>
      }
      <input
        autoFocus={true}
        aria-label="Username"
        type="text"
        placeholder="Username"
        className={styles.input}
        ref={userNameInput}
        value={username}
        onChange={(e) => changeUsername(e.target.value)}
        autoComplete="username"
        aria-required="true"
      />
      <input
        aria-label="Password"
        type="password"
        placeholder="Password"
        className={styles.input}
        ref={passwordInput}
        value={password}
        onChange={(e) => changePassword(e.target.value)}
        autoComplete="new-password"
        aria-required="true"
      />
      <input
        aria-label="Repeat password"
        type="password"
        placeholder="Repeat password"
        className={styles.input}
        ref={repeatPasswordInput}
        value={passwordRepeat}
        onChange={(e) => changeRepeatPassword(e.target.value)}
        autoComplete="new-password"
        aria-required="true"
      />
      <button
        type="submit"
        aria-label="Register"
        aria-description="Register"
        className={styles.button}
      >
        Register
      </button>
      <p className={styles.switchText}>
        Already have an account? <button
          aria-description="Switch to loginform"
          tabIndex={0}
          className={styles.switchLink}
          onClick={onSwitch}>
          Login
        </button>
      </p>
    </form>
  );
};

export default ModalRegisterForm;
