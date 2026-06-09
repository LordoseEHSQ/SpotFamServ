import { BrowserRouter } from 'react-router-dom';
import { Toaster } from 'sonner';
import { AppRoutes } from './routes';
import { Layout } from './components/layout/Layout';
import { AuthProvider } from './hooks/useAuth';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Layout>
          <AppRoutes />
        </Layout>
      </AuthProvider>
      <Toaster richColors position="top-right" />
    </BrowserRouter>
  );
}
