/* const PUBLIC_ENDPOINTS = [
    '/api/auth/register',
    '/api/post/public',
    '/api/profileimage'
];

const LOGIN_ENDPOINT = '/api/auth/login';
const LOGOUT_ENDPOINT = '/api/auth/logout';

const USER_OBJECT_ENDPOINT = '/api/auth/check-auth';

let token = null;
let tokenExpires = null;

let authenticated = false;
let user = null;

let fetchTokenPromise = null;


let debugMode = false;

const authChannel = new BroadcastChannel("auth_channel");
const debugChannel = new BroadcastChannel('debug_channel');

debugChannel.onmessage = (event) => {
    if (event.data === 'ENABLE_DEBUG') {
        debugMode = true;
        debugLog('Debug mode ENABLED');
    } else if (event.data === 'DISABLE_DEBUG') {
        debugLog('Debug mode DISABLED');
        debugMode = false;
    }
};

function debugLog(...args) {
    if (debugMode) {
        console.log('[SW DEBUG]:', ...args);
    }
}

function debugError(...args) {
    if (debugMode) {
        console.log('[SW ERROR]:', ...args);
    }
}

authChannel.onmessage = async (event) => {
    if (event.data === 'AUTH') {
        debugLog('AUTH MESSAGE RECEIVED');

        if (!authenticated || !user) {
            try {
                request = new Request(USER_OBJECT_ENDPOINT);
                const response = await addAuthHeaders(request);

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                jsonResponse = await response.json();
                user = jsonResponse.user;
                authenticated = true
                sendAuthMessage(true);
                return;
            } catch (error) {
                debugError('Ett fel uppstod:', error);
                sendAuthMessage(false);
                return;
            }
        }

        if (authenticated && user) {
            debugLog('User already authenticated:', user);
            sendAuthMessage(true);
            return;
        }

        sendAuthMessage(false);
        return;
    }
};
function sendAuthMessage(success) {
    if (success) {
        authChannel.postMessage({
            message: 'AUTHENTICATED',
            user: user
        });
        debugLog('AUTHENTICATED MESSAGE SENT', user);
    } else {
        authChannel.postMessage({
            message: 'NOT_AUTHENTICATED'
        });
        debugLog('NOT_AUTHENTICATED MESSAGE SENT');
    }
}

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
});

async function handleLogout(request) {
    const response = await fetch(request);
    const emptyResponse = new Response(null, {
        status: response.status,
        statusText: response.statusText,
        headers: response.headers,
        data: response.data
    });
    if (response.ok) {
        const data = await response.json();
        token = null;
        tokenExpires = null;
        authenticated = false;
        user = null;
        sendAuthMessage(false);
    }
    return emptyResponse;
}

async function handleLogin(request) {
    const response = await fetch(request);
    if (response.ok) {
        const data = await response.json();
        const emptyResponse = new Response(null, {
            status: response.status,
            statusText: response.statusText,
            headers: response.headers,
            data: response.data
        });
        token = data.access_token;
        tokenExpires = getExpiryFromToken(token) * 1000 - 10000;
        authenticated = true;
        user = data.user;
        sendAuthMessage(true);
        return emptyResponse;
    } else {
        token = null;
        tokenExpires = null;
        authenticated = false;
        user = null;

        sendAuthMessage(false);
    }

    return response;
}

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (url.pathname.startsWith(LOGIN_ENDPOINT)) {
        event.respondWith(handleLogin(event.request));
        return;
    }

    if (url.pathname.startsWith(LOGOUT_ENDPOINT)) {
        event.respondWith(handleLogout(event.request));
        return;
    }

    if (!authenticated || PUBLIC_ENDPOINTS.some(endpoint => url.pathname.startsWith(endpoint))) {
        return;
    }
    if (url.pathname.startsWith('/api')) {
        event.respondWith(addAuthHeaders(event.request));
        return;
    }
});


async function addAuthHeaders(request) {
    if (!token || Date.now() > tokenExpires) {
        if (!fetchTokenPromise) {
            fetchTokenPromise = fetchToken();
        }
        await fetchTokenPromise
            .finally(() => {
                fetchTokenPromise = null;
            });
    }
    const modifiedHeaders = new Headers(request.headers);
    modifiedHeaders.set('Authorization', `Bearer ${token}`);

    const modifiedRequest = new Request(request, {
        headers: modifiedHeaders,
        credentials: 'same-origin'
    });

    const response = await fetch(modifiedRequest);

    return response;
}

async function fetchToken() {
    debugLog('Fetching token...');
    try {
        const response = await fetch('/api/auth/token');
        if (!response.ok) throw new Error('Misslyckades att hämta token');

        const data = await response.json();

        token = data.access_token;
        tokenExpires = getExpiryFromToken(token) * 1000 - 10000;
        debugLog('Token updated:', token);
        debugLog('Token valid to:', new Date(tokenExpires));
    } catch (error) {
        debugError('Could not fetch token:', error);
    }
}

function decodeJwt(token) {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace('-', '+').replace('_', '/');
    const jsonPayload = decodeURIComponent(atob(base64).split('').map(function (c) {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
    return JSON.parse(jsonPayload);
}

function getExpiryFromToken(token) {
    const decoded = decodeJwt(token);
    return decoded.exp;
} */