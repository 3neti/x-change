export type CockpitPayeeKind =
  "open" | "mobile" | "email" | "vendor" | "secret" | "invalid";

export type CockpitPayeePolicy = {
  kind: CockpitPayeeKind;
  normalizedValue: string | null;
  displayValue: string;
  explicitSecret: boolean;
  issuable: boolean;
  message: string;
};

const SECRET_MIN_LENGTH = 4;
const SECRET_MAX_LENGTH = 255;

export function classifyCockpitPayee(value: string): CockpitPayeePolicy {
  const input = value.trim();

  if (input === "" || input.toUpperCase() === "CASH") {
    return policy(
      "open",
      null,
      "Open Recipient",
      false,
      true,
      "Anyone who meets the other claim requirements may claim.",
    );
  }

  const quoted = quotedSecret(input);

  if (quoted !== null) {
    return secretPolicy(quoted, true);
  }

  if (input.startsWith('"') || input.endsWith('"')) {
    return invalidPolicy(
      "Close both double quotes to use this value as a release code.",
    );
  }

  const mobile = normalizePhilippineMobile(input);

  if (mobile !== null) {
    return policy(
      "mobile",
      mobile,
      maskMobile(mobile),
      false,
      true,
      "Mobile match and OTP will be required.",
    );
  }

  if (looksLikePhilippineMobile(input)) {
    return invalidPolicy(
      "Enter a complete Philippine mobile number, or wrap it in double quotes to use it as a release code.",
    );
  }

  if (isValidEmail(input)) {
    return policy(
      "email",
      input.toLowerCase(),
      input.toLowerCase(),
      false,
      false,
      "Email-bound Pay Codes require email OTP, which is not available yet.",
    );
  }

  if (input.includes("@") && !input.startsWith("@")) {
    return invalidPolicy(
      "Enter a complete email address, or wrap it in double quotes to use it as a release code.",
    );
  }

  if (input.startsWith("@")) {
    if (!/^@[A-Za-z0-9][A-Za-z0-9._-]{0,19}$/.test(input)) {
      return invalidPolicy(
        "Vendor aliases must start with @ and contain only letters, numbers, periods, underscores, or hyphens.",
      );
    }

    const alias = input.slice(1).toUpperCase();

    return policy(
      "vendor",
      alias,
      `@${alias}`,
      false,
      true,
      "The claimant must use this vendor alias.",
    );
  }

  return secretPolicy(input, false);
}

function quotedSecret(value: string): string | null {
  if (value.length < 2 || !value.startsWith('"') || !value.endsWith('"')) {
    return null;
  }

  return value.slice(1, -1);
}

function secretPolicy(value: string, explicit: boolean): CockpitPayeePolicy {
  if (value.length < SECRET_MIN_LENGTH || value.length > SECRET_MAX_LENGTH) {
    return invalidPolicy("Release codes must contain 4 to 255 characters.");
  }

  return policy(
    "secret",
    value,
    "Release Code",
    explicit,
    true,
    explicit
      ? "Quoted value will be required as a release code."
      : "This value will be required as a release code.",
  );
}

function normalizePhilippineMobile(value: string): string | null {
  const normalized = value.trim().replace(/[\s().-]+/g, "");

  if (/^\+639\d{9}$/.test(normalized)) {
    return normalized;
  }

  if (/^639\d{9}$/.test(normalized)) {
    return `+${normalized}`;
  }

  if (/^09\d{9}$/.test(normalized)) {
    return `+63${normalized.slice(1)}`;
  }

  if (/^9\d{9}$/.test(normalized)) {
    return `+63${normalized}`;
  }

  return null;
}

function looksLikePhilippineMobile(value: string): boolean {
  const normalized = value.trim().replace(/[\s().-]+/g, "");

  return /^(?:\+?63|0?9)/.test(normalized);
}

function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function maskMobile(value: string): string {
  return `Mobile ending ${value.slice(-4)}`;
}

function invalidPolicy(message: string): CockpitPayeePolicy {
  return policy("invalid", null, "Needs Attention", false, false, message);
}

function policy(
  kind: CockpitPayeeKind,
  normalizedValue: string | null,
  displayValue: string,
  explicitSecret: boolean,
  issuable: boolean,
  message: string,
): CockpitPayeePolicy {
  return {
    kind,
    normalizedValue,
    displayValue,
    explicitSecret,
    issuable,
    message,
  };
}
