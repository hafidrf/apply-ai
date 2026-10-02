import { useState } from "react";
import ChatComposer from "./ChatComposer";
import { Select } from "./ui";
import type { LlmKey, Profile } from "../lib/api";

/**
 * Composer lowongan — memakai ChatComposer bersama,
 * ditambah pemilih profil & kanal.
 */
export default function JobComposer({
  profiles,
  keys,
  onCreated,
  onError,
}: {
  profiles: Profile[];
  keys: LlmKey[];
  onCreated: (job: { id: number; input_meta?: Record<string, unknown> }) => void;
  onError: (msg: string) => void;
}) {
  const [profileId, setProfileId] = useState("");
  const [channel, setChannel] = useState("");

  const confirmedProfiles = profiles.filter((p) => p.confirmed);

  return (
    <ChatComposer
      keys={keys}
      hint="paste teks · Ctrl+V gambar · drag & drop · PDF · link"
      submitLabel="Kirim"
      busyLabel="AI sedang mengekstrak info lowongan..."
      placeholder={`Tempel apa saja di sini:

• Teks lowongan (copy dari mana pun)
• Screenshot lowongan — paste langsung dengan Ctrl+V
• Link lowongan (mis. https://...) — isinya dibaca otomatis
• Atau tarik file gambar/PDF ke area ini`}
      footerExtra={
        <>
          <div className="w-44">
            <label className="text-xs text-slate-500 mb-1 block">Pakai profil</label>
            <Select value={profileId} onChange={(e) => setProfileId(e.target.value)}>
              <option value="">— profil terkonfirmasi terbaru —</option>
              {confirmedProfiles.map((p) => (
                <option key={p.id} value={p.id}>{p.data.nama ?? "Profil"} ({p.id})</option>
              ))}
            </Select>
          </div>
          <div className="w-44">
            <label className="text-xs text-slate-500 mb-1 block">Kanal (opsional)</label>
            <Select value={channel} onChange={(e) => setChannel(e.target.value)}>
              <option value="">— deteksi otomatis —</option>
              <option value="email">Email</option>
              <option value="linkedin">LinkedIn DM</option>
              <option value="whatsapp">WhatsApp DM</option>
              <option value="dm">DM Sosial (Threads/IG/X)</option>
              <option value="portal">Portal / Cover letter</option>
            </Select>
          </div>
        </>
      }
      appendExtra={(form) => {
        if (profileId) form.append("profile_id", profileId);
        if (channel) form.append("channel_override", channel);
      }}
      onError={onError}
      onSend={async (form, ctx) => {
        form.append("input_type", ctx.hasImages ? "screenshot" : "text");

        const resp = await fetch("/api/jobs", {
          method: "POST",
          headers: { Authorization: `Bearer ${localStorage.getItem("applyai_token")}` },
          body: form,
        });
        const json = await resp.json();
        if (!resp.ok) throw new Error(json.message ?? `Gagal (${resp.status})`);
        onCreated(json);
      }}
    />
  );
}
