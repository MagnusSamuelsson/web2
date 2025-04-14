import styles from "./LoadingSpinner.module.css";

const LoadingSpinner : React.FC<{ text?: string }> = ({ text = "Loading..." }) => {
  return (
    <div className={styles.loadingContainer}>
      <div className={styles.spinner}></div>
      <p className={styles.p}>{text}</p>
    </div>
  );
};

export default LoadingSpinner;