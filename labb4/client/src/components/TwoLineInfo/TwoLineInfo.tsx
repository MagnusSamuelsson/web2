interface TwoLineInfoProps {
  top: string;
  bottom: string;
  className?: string;
}

const TwoLineInfo: React.FC<TwoLineInfoProps> = ({ top, bottom, className }) => {
    return (
      <span className={className}>
        <span style={{ display: "block" }}>{top}</span>
        <span style={{ display: "block" }}>{bottom}</span>
      </span>
    );
  };

  export default TwoLineInfo;