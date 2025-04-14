import React from "react";

interface CommentIconProps {
    className?: string;
}

const CommentSymbol: React.FC<CommentIconProps> = ({
    className
}) => {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 9.79 9.79 0 0 1-4-.8L3 21l2-4.5a8.38 8.38 0 0 1-2-5.5 8.5 8.5 0 1 1 17 0z"></path>
        </svg>
    );
};

export default CommentSymbol;
