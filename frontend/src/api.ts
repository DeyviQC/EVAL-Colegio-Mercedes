let token = "";
export async function request<T>(
  path: string,
  data?: Record<string, unknown> | FormData,
): Promise<T> {
  const response = await fetch(`/api/${path}`, {
    credentials: "same-origin",
    method: data ? "POST" : "GET",
    headers: {
      Accept: "application/json",
      ...(data
        ? {
            ...(data instanceof FormData
              ? {}
              : { "Content-Type": "application/json" }),
            "X-CSRF-TOKEN": token,
          }
        : {}),
    },
    body:
      data instanceof FormData ? data : data ? JSON.stringify(data) : undefined,
    signal: AbortSignal.timeout(20000),
  }).catch(() => {
    throw new Error(
      data
        ? "No se pudo confirmar el resultado. Actualiza antes de repetir la operación."
        : "No se pudo conectar con el servidor local. Comprueba que esté iniciado y vuelve a intentar.",
    );
  });
  const result = await response.json().catch(() => ({
    message: "El servidor no devolvió una respuesta válida.",
  }));
  if (!response.ok)
    throw new Error(result.message ?? "No se pudo completar la operación.");
  return result as T;
}
export async function session() {
  const result = await request<{ csrf_token: string }>("session");
  token = result.csrf_token;
}
