import { useState } from 'react';
interface StepProfileProps {
  profileName: string;
  profileDescription: string | null;
  onSubmit: (payload: { name: string; description: string | null }) => void;
  isSubmitting: boolean;
  error: string | null;
}

export function StepProfile({
  profileName,
  profileDescription,
  onSubmit,
  isSubmitting,
  error,
}: StepProfileProps) {
  const [name, setName] = useState(profileName || '');
  const [description, setDescription] = useState(profileDescription ?? '');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    onSubmit({ name: name.trim(), description: description.trim() || null });
  };

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Profil bearbeiten</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Name und optionale Beschreibung für dieses Familienprofil.
      </p>
      {error && (
        <p style={{ color: '#dc2626', marginBottom: '1rem', fontSize: 14 }} role="alert">
          {error}
        </p>
      )}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '1rem' }}>
          <label htmlFor="profile-name" style={{ display: 'block', marginBottom: 4, fontWeight: 500 }}>
            Name *
          </label>
          <input
            id="profile-name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            disabled={isSubmitting}
            style={{ width: '100%', maxWidth: 320, padding: '0.5rem', border: '1px solid #d1d5db', borderRadius: 6 }}
          />
        </div>
        <div style={{ marginBottom: '1rem' }}>
          <label htmlFor="profile-desc" style={{ display: 'block', marginBottom: 4, fontWeight: 500 }}>
            Beschreibung
          </label>
          <textarea
            id="profile-desc"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            disabled={isSubmitting}
            rows={2}
            style={{ width: '100%', maxWidth: 400, padding: '0.5rem', border: '1px solid #d1d5db', borderRadius: 6 }}
          />
        </div>
        <button type="submit" disabled={isSubmitting || !name.trim()} style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}>
          {isSubmitting ? 'Speichern…' : 'Weiter'}
        </button>
      </form>
    </div>
  );
}
