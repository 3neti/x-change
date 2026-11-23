export type IssuanceFieldError = {
  path?: string;
  field: string;
  message: string;
};
export type RiderUrlPolicy = {
  allowed_schemes?: string[];
  allowed_hosts?: string[];
  allow_any_host?: boolean;
};

export function issuanceFieldLabel(path: string): string {
  const labels: Record<string, string> = {
    "rider.url": "Rider → Link",
    "rider.message": "Purpose",
    "cash.amount": "Amount",
    amount: "Amount",
    target_amount: "Total to Collect",
    "feedback.mobile": "Status Updates → SMS",
    "feedback.email": "Status Updates → Email",
    "feedback.webhook": "Status Updates → Webhook",
    "rider.splash": "Rider → Splash",
  };
  return (
    labels[path] ??
    path
      .replace(/\.\d+\./g, ".")
      .replace(/[_\.]+/g, " ")
      .replace(/\b\w/g, (value) => value.toUpperCase())
  );
}

export function issuanceFailure(
  status: number,
  body: Record<string, unknown>,
): { heading: string; errors: IssuanceFieldError[] } {
  if (
    status === 422 &&
    body.errors &&
    typeof body.errors === "object" &&
    !Array.isArray(body.errors)
  ) {
    const errors = Object.entries(body.errors).flatMap(([path, messages]) =>
      (Array.isArray(messages) ? messages : [messages])
        .filter(
          (message): message is string =>
            typeof message === "string" && message.trim() !== "",
        )
        .map((message) => ({ path, field: issuanceFieldLabel(path), message })),
    );
    if (errors.length)
      return { heading: "Fix these fields before issuing", errors };
  }
  const states: Record<number, [string, string, string]> = {
    0: [
      "Connection issue",
      "Connection",
      "The request could not be completed. Check Pay Codes before trying again. Your draft is preserved.",
    ],
    200: [
      "Issuance result unavailable",
      "Response",
      "We could not confirm the issuance result. Check Pay Codes before trying again.",
    ],
    401: [
      "Sign in again",
      "Session",
      "Your session has expired. Sign in again, then return to your draft.",
    ],
    419: [
      "Session expired",
      "Session",
      "Your session has expired. Refresh and sign in again before submitting.",
    ],
    403: [
      "Request not permitted",
      "Access",
      "Access or a security check rejected this request. No field-specific error was returned.",
    ],
    422: [
      "Instruction could not be validated",
      "Validation",
      "The server rejected the instruction but did not identify a field.",
    ],
    429: [
      "Please wait before trying again",
      "Rate limit",
      "Too many requests were made. Wait before checking or submitting again.",
    ],
  };
  const [heading, field, message] =
    states[status] ??
    (status >= 500
      ? [
          "Service temporarily unavailable",
          "Server",
          "The service could not complete the request. Check Pay Codes before trying again.",
        ]
      : [
          "Request could not be completed",
          "Response",
          "An unexpected response was received. Check Pay Codes before trying again.",
        ]);
  return { heading, errors: [{ field, message }] };
}

export function riderUrlError(
  value: string,
  policy: RiderUrlPolicy = {},
): string | null {
  const url = value.trim();
  if (!url) return null;
  try {
    const parsed = new URL(url);
    if (
      url.length > 4096 ||
      /[\x00-\x20\x7f]/.test(url) ||
      !parsed.hostname ||
      parsed.username ||
      parsed.password ||
      parsed.searchParams.has("x_rider_handoff")
    )
      throw new Error();
    if (
      !(policy.allowed_schemes ?? ["http", "https"]).includes(
        parsed.protocol.slice(0, -1).toLowerCase(),
      )
    )
      throw new Error();
    if (
      policy.allow_any_host === false &&
      !(policy.allowed_hosts ?? [])
        .map((host) => host.toLowerCase())
        .includes(parsed.hostname.toLowerCase())
    )
      return "This URL is not permitted by the redirect policy.";
    return null;
  } catch {
    return "Enter a valid URL, for example https://example.com/page.";
  }
}

export async function urlEvidence(
  value: string,
): Promise<{ present: boolean; length: number; fingerprint: string | null }> {
  const url = value.trim();
  let fingerprint: string | null = null;
  if (url && globalThis.crypto?.subtle) {
    try {
      const digest = await crypto.subtle.digest(
        "SHA-256",
        new TextEncoder().encode(url),
      );
      fingerprint = Array.from(new Uint8Array(digest), (byte) =>
        byte.toString(16).padStart(2, "0"),
      ).join("");
    } catch {
      /* Diagnostic hashing must not prevent issuance. */
    }
  }
  return { present: url !== "", length: url.length, fingerprint };
}
