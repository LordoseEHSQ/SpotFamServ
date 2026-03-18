import { BrowserRouter } from 'react-router-dom';
import { AppRoutes } from './routes';
import { Layout } from './components/layout/Layout';

export default function App() {
  return (
    <BrowserRouter>
      <Layout>
        <AppRoutes />
      </Layout>
    </BrowserRouter>
  );
}
