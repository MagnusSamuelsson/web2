interface FetchOptions extends RequestInit {
    auth?: boolean;
}

class ApiClient {
    async request<T>(
        url: string,
        { auth = true, ...options }: FetchOptions = {},
        token?: string,
        blob: boolean = false
    ): Promise<T> {

        try {
            const headers: Record<string, string> = {
                "Content-Type": "application/json",
                ...(options.headers as Record<string, string>),
            };

            if (auth && token) {
                headers["Authorization"] = `Bearer ${token}`;
            }

            const response = await fetch(url, { ...options, headers });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            if (blob) {
                return response.blob() as unknown as T;
            }
            return await response.json();
        } catch (error) {
            throw error instanceof Error ? error : new Error("Ett okänt fel inträffade");
        }
    }

    get<T>(url: string, auth = true, token?: string) {
        return this.request<T>(url, { method: "GET", auth }, token);
    }
    getBlob<Blob>(url: string, auth = true, token?: string) {
        return this.request<Blob>(url, { method: "GET", auth }, token, true);
    }
    post<T>(url: string, data: object, auth = true, token?: string) {
        return this.request<T>(url, { method: "POST", body: JSON.stringify(data), auth }, token);
    }
    postBlob<T>(url: string, data: Blob, auth = true, token: string = "") {
        if (data.size > 1024 * 1024 * 10) {
            throw new Error("Bilden blev för stor! Max 10 MB");
        }
        const body : BodyInit = data;
        return this.request<T>(url, { method: "POST", body, auth, headers: { "Content-Type": "application/octet-stream" } }, token);
    }
    put<T>(url: string, data: object, auth = true, token?: string) {
        return this.request<T>(url, { method: "PUT", body: JSON.stringify(data), auth }, token);
    }
    delete<T>(url: string, auth = true, token?: string) {
        return this.request<T>(url, { method: "DELETE", auth }, token);
    }
}

const api = new ApiClient();
export default api;
