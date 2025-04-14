import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import PostsPageAuth from './pages/PostsPage/PostsPageAuth';
import UserPage from './pages/UserPage/UserPage';
import UserPagePublic from './pages/UserPage/UserPagePublic';
import AuthWrapper from './layouts/AuthWrapper';
import { AppLayout } from './layouts/AppLayout';
import ProfilePage from "./pages/ProfilePage/ProfilePage";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "./services/queryClient";
import PostsPage from "./pages/PostsPage/PostsPage";
import AuthRouter from "./layouts/AuthRouter";
import PostDetailView from "./components/PostDetailView/PostDetailView";

function App() {
  return (
    <Router>
      <QueryClientProvider client={queryClient}>
        <AuthWrapper>
          <Routes>
            <Route path="/" element={<AppLayout />} >
                  <Route index element={<AuthRouter AuthElement={<PostsPageAuth/>} NoAuthElement={<PostsPage />} />} />
                  <Route path="/post/:id" element={<AuthRouter AuthElement={<PostDetailView/>} NoAuthElement={<PostsPage />} />} />
                  <Route path="/:id" element={<AuthRouter AuthElement={<PostsPageAuth/>} NoAuthElement={<PostsPage />} />} />
                  <Route path="/user/:id" element={<AuthRouter AuthElement={<UserPage/>} NoAuthElement={<UserPagePublic />} />} />
                  <Route path="/profile" element={<AuthRouter AuthElement={<ProfilePage/>} />} />
              <Route path="*" element={<PostsPage />} />
            </Route>
          </Routes>
        </AuthWrapper>
      </QueryClientProvider>
    </Router>
  )
}

export default App
