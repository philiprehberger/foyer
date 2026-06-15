export type Config = {
  apiBase: string;
  businessId: string;
  position: "bottom-right" | "bottom-left";
  theme: "auto" | "light" | "dark";
};

export type Role = "customer" | "agent" | "system";

export type WidgetMessage = {
  id: string;
  role: Role;
  text: string;
  attachments?: Array<{
    content_type: string;
    url: string;
    name?: string;
  }>;
  created_at: string;
};

export type SessionMint = {
  session_id: string;
  token: string;
  expires_at: string;
};

export type VerifyPhaseIssued = {
  status: "code_issued";
  expires_at: string;
};

export type VerifyPhaseVerified = {
  status: "verified";
  resumed_conversation_id: string | null;
};

export type VerifyPhaseBlocked = {
  status: "consent_blocked";
  reason: string;
};

export type VerifyResponse =
  | VerifyPhaseIssued
  | VerifyPhaseVerified
  | VerifyPhaseBlocked;
