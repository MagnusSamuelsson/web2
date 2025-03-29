import { ReactNode, useCallback, useEffect, useRef } from "react";

interface InfiniteScrollProps {
    children: ReactNode;
    fetchNextPage: () => void;
    hasNextPage: boolean;
}

export default function InfiniteScroll({
    children,
    fetchNextPage,
    hasNextPage,
}: InfiniteScrollProps) {
  const blockInfScroll = useRef(true);
  const debounceTimeoutRef = useRef<ReturnType<typeof setTimeout>>(null);

    const handleScroll = useCallback(() => {
        const scrollPosition = window.innerHeight + document.documentElement.scrollTop;
        const documentHeight = document.documentElement.offsetHeight;
        if (debounceTimeoutRef.current) {
            clearTimeout(debounceTimeoutRef.current);
        }
        if (blockInfScroll.current && scrollPosition < documentHeight) {
            blockInfScroll.current = false;
        }
        debounceTimeoutRef.current = setTimeout(() => {
            if (!blockInfScroll.current && scrollPosition >= documentHeight - 100 && hasNextPage) {
                fetchNextPage();
            }
        }, 100);
    }, [fetchNextPage, hasNextPage]);

    useEffect(() => {
        window.addEventListener("scroll", handleScroll);
        return () => {
            window.removeEventListener("scroll", handleScroll);
        };
    }, [handleScroll])
    return (
        <>
            {children}
        </>
    );
}