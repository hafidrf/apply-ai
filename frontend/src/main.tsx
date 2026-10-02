import { StrictMode, Suspense, lazy } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import "./index.css";
import Layout from "./components/Layout";
import ErrorBoundary from "./components/ErrorBoundary";
import { getToken } from "./lib/api";

// Halaman dimuat sesuai kebutuhan (code splitting) supaya bundle awal kecil dan
// aplikasi terasa cepat dibuka. Halaman berat (Settings, Job detail) tidak lagi
// ikut di payload pertama.
const LoginPage = lazy(() => import("./pages/LoginPage"));
const RegisterPage = lazy(() => import("./pages/RegisterPage"));
const SettingsPage = lazy(() => import("./pages/SettingsPage"));
const ProfilePage = lazy(() => import("./pages/ProfilePage"));
const JobsPage = lazy(() => import("./pages/JobsPage"));
const JobDetailPage = lazy(() => import("./pages/JobDetailPage"));
const HistoryPage = lazy(() => import("./pages/HistoryPage"));

function PageLoading() {
  return (
    <div className="flex items-center justify-center gap-2 py-12 text-sm text-slate-500">
      <span className="inline-block w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
      Memuat...
    </div>
  );
}

function RequireAuth({ children }: { children: React.ReactNode }) {
  if (!getToken()) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

const container = document.getElementById("root")!;

const tree = (
  <StrictMode>
    <ErrorBoundary>
      <BrowserRouter>
        <Suspense fallback={<PageLoading />}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route element={<RequireAuth><Layout /></RequireAuth>}>
              <Route path="/" element={<Navigate to="/jobs" replace />} />
              <Route path="/settings" element={<SettingsPage />} />
              <Route path="/profile" element={<ProfilePage />} />
              <Route path="/jobs" element={<JobsPage />} />
              <Route path="/jobs/:id" element={<JobDetailPage />} />
              <Route path="/history" element={<HistoryPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/jobs" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </ErrorBoundary>
  </StrictMode>
);

// Jaring pengaman terakhir: error yang lolos dari React tetap tercatat, bukan hilang diam-diam.
window.addEventListener("unhandledrejection", (e) => {
  console.error("[unhandled rejection]", e.reason);
});
window.addEventListener("error", (e) => {
  console.error("[window error]", e.message);
});

// Simpan root di modul-global supaya HMR tidak membuat root kedua
const globalScope = globalThis as unknown as { __applyaiRoot?: ReturnType<typeof createRoot> };
const root = (globalScope.__applyaiRoot ??= createRoot(container));
root.render(tree);
