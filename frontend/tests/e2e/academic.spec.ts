import { test, expect } from "@playwright/test";

async function enter(
  page: import("@playwright/test").Page,
  account: string,
  room = true,
) {
  await page.goto("/");
  await page.getByLabel("Correo electrónico").fill(account);
  await page.getByLabel("Contraseña", { exact: true }).fill("Mercedes2026!");
  await page.getByRole("button", { name: "Ingresar" }).click();
  await expect(page.locator(".account")).toBeVisible();
  if (room) {
    await page
      .getByRole("combobox", { name: "Grado", exact: true })
      .selectOption({ label: "3° de secundaria" });
    await page
      .getByRole("combobox", { name: "Salón", exact: true })
      .selectOption({ label: "A" });
  }
}

test.beforeEach(async ({ context, page }) => {
  page.on("pageerror", (error) =>
    console.error("Browser error:", error.message),
  );
  page.on("requestfailed", (request) =>
    console.error(
      "Request failed:",
      request.url(),
      request.failure()?.errorText,
    ),
  );
  await context.route("**/*", (route) => {
    const url = new URL(route.request().url());
    return ["127.0.0.1", "localhost"].includes(url.hostname)
      ? route.continue()
      : route.abort();
  });
});

test("login bootstrap loads across isolated browser contexts", async ({
  browser,
}) => {
  test.setTimeout(90000);
  for (let index = 0; index < 12; index++) {
    const context = await browser.newContext({
      baseURL: test.info().project.use.baseURL,
    });
    await context.route("**/*", (route) =>
      ["127.0.0.1", "localhost"].includes(
        new URL(route.request().url()).hostname,
      )
        ? route.continue()
        : route.abort(),
    );
    const target = await context.newPage();
    target.on("pageerror", (error) =>
      console.error(`Bootstrap ${index}:`, error.message),
    );
    target.on("requestfailed", (request) =>
      console.error(
        `Bootstrap ${index}:`,
        request.url(),
        request.failure()?.errorText,
      ),
    );
    try {
      await target.goto("/");
      await expect(target.getByLabel("Correo electrónico")).toBeVisible({
        timeout: 10000,
      });
    } catch (error) {
      await target
        .screenshot({
          path: `artifacts/bootstrap-failure-${index}.png`,
          timeout: 5000,
        })
        .catch(() => {});
      throw error;
    } finally {
      await context.close();
    }
  }
});

for (const [role, email] of [
  ["Dirección", "director"],
  ["Subdirección", "subdirector"],
  ["Docente", "docente"],
  ["Estudiante", "estudiante"],
] as const) {
  test(`${role} works with external network requests denied`, async ({
    page,
  }) => {
    await page.goto("/");
    await page.getByLabel("Correo electrónico").fill(`${email}@eval.test`);
    await page.getByLabel("Contraseña", { exact: true }).fill("Mercedes2026!");
    await page.getByRole("button", { name: "Ingresar" }).click();
    await expect(page.locator(".account")).toContainText(role);
    await expect(
      page.getByRole("button", { name: "Cerrar sesión" }),
    ).toBeVisible();
    if (role === "Subdirección") {
      await expect(
        page.getByRole("button", { name: "Matrículas", exact: true }),
      ).toHaveCount(0);
      await expect(
        page.getByRole("button", { name: "Períodos", exact: true }),
      ).toHaveCount(0);
    }
    await page
      .getByRole("combobox", { name: "Grado", exact: true })
      .selectOption({ label: "3° de secundaria" });
    await page
      .getByRole("combobox", { name: "Salón", exact: true })
      .selectOption({ label: "A" });
    await expect(
      page.getByRole("heading", { name: "Matemática", exact: true }),
    ).toBeVisible();
    await expect(page.locator(".cards article")).toHaveCount(
      role === "Docente" ? 1 : 6,
    );
    if (role === "Dirección")
      await page.screenshot({
        path: "artifacts/academic-director.png",
        fullPage: true,
      });
    await page.getByRole("button", { name: "Cerrar sesión" }).click();
    await expect(page.getByRole("button", { name: "Ingresar" })).toBeVisible();
  });
}

test("teacher publishes and student submits through the original assignment", async ({
  page,
  browser,
}) => {
  const title = `Actividad conectada ${Date.now()}`;
  async function loginAs(target: typeof page, email: string) {
    await target.goto("/");
    await target.getByLabel("Correo electrónico").fill(email);
    await target
      .getByLabel("Contraseña", { exact: true })
      .fill("Mercedes2026!");
    await target.getByRole("button", { name: "Ingresar" }).click();
    await expect(target.locator(".account")).toBeVisible();
    await target
      .getByRole("combobox", { name: "Grado", exact: true })
      .selectOption({ label: "3° de secundaria" });
    await target
      .getByRole("combobox", { name: "Salón", exact: true })
      .selectOption({ label: "A" });
    await target
      .getByRole("button", { name: "Actividades", exact: true })
      .click();
  }
  await loginAs(page, "docente@eval.test");
  await page.getByText("Crear actividad", { exact: true }).click();
  await page.getByLabel("Título", { exact: true }).fill(title);
  await page
    .getByLabel("Instrucciones", { exact: true })
    .fill("Responde con un ejemplo.");
  await page
    .getByRole("button", { name: "Publicar actividad", exact: true })
    .click();
  await expect(page.getByRole("heading", { name: title })).toBeVisible();
  const context = await browser.newContext({
    baseURL: test.info().project.use.baseURL,
  });
  await context.route("**/*", (route) =>
    ["127.0.0.1", "localhost"].includes(new URL(route.request().url()).hostname)
      ? route.continue()
      : route.abort(),
  );
  const studentPage = await context.newPage();
  await loginAs(studentPage, "estudiante@eval.test");
  const activity = studentPage
    .locator("article")
    .filter({ has: studentPage.getByRole("heading", { name: title }) });
  await activity
    .getByLabel("Respuesta", { exact: true })
    .fill("Respuesta desde mi matrícula activa.");
  await activity
    .getByRole("button", { name: "Enviar al docente original" })
    .click();
  await expect(
    activity.getByRole("button", { name: "Enviar al docente original" }),
  ).toHaveCount(0);
  await page.getByRole("button", { name: "Actualizar", exact: true }).click();
  await page.getByRole("button", { name: "Entregas", exact: true }).click();
  await expect(
    page
      .locator("article")
      .filter({ has: page.getByRole("heading", { name: title }) }),
  ).toContainText("Respuesta desde mi matrícula activa.");
  const delivery = page
    .locator("article")
    .filter({ has: page.getByRole("heading", { name: title }) });
  await delivery
    .getByRole("combobox", { name: "Calificación", exact: true })
    .selectOption("AD");
  await delivery
    .getByLabel("Retroalimentación", { exact: true })
    .fill("Excelente trabajo desde el flujo completo.");
  await delivery
    .getByRole("button", { name: "Guardar calificación", exact: true })
    .click();
  await expect(delivery).toContainText("Nota AD");
  await studentPage
    .getByRole("button", { name: "Actualizar", exact: true })
    .click();
  await studentPage
    .getByRole("button", { name: "Mis notas", exact: true })
    .click();
  await expect(
    studentPage
      .locator("article")
      .filter({ has: studentPage.getByRole("heading", { name: title }) }),
  ).toContainText("Excelente trabajo desde el flujo completo.");
  await context.close();
});

test("material upload is immediately available in the student library", async ({
  page,
  browser,
}) => {
  const title = `Material conectado ${Date.now()}`;
  await enter(page, "docente@eval.test");
  await page.getByRole("button", { name: "Materiales", exact: true }).click();
  await page
    .locator("summary")
    .filter({ hasText: "Publicar material" })
    .click();
  const uploadForm = page
    .locator("form")
    .filter({
      has: page.getByRole("button", { name: "Publicar material", exact: true }),
    });
  await uploadForm.getByLabel("Título", { exact: true }).fill(title);
  await uploadForm
    .getByLabel("Contenido", { exact: true })
    .fill("Guía de aprendizaje publicada sin aprobación.");
  await uploadForm.getByLabel("Adjunto", { exact: false }).setInputFiles({
    name: "guia.txt",
    mimeType: "text/plain",
    buffer: Buffer.from("Contenido del recurso educativo."),
  });
  await page
    .getByRole("button", { name: "Publicar material", exact: true })
    .click();
  await expect(page.getByRole("heading", { name: title })).toBeVisible();
  const context = await browser.newContext({
    baseURL: test.info().project.use.baseURL,
  });
  const pupil = await context.newPage();
  await enter(pupil, "estudiante@eval.test");
  await pupil.getByRole("button", { name: "Biblioteca", exact: true }).click();
  const card = pupil
    .locator("article")
    .filter({ has: pupil.getByRole("heading", { name: title }) });
  await expect(card).toContainText(
    "Guía de aprendizaje publicada sin aprobación.",
  );
  const download = await Promise.all([
    pupil.waitForEvent("download"),
    card.getByRole("link", { name: "Descargar guia.txt" }).click(),
  ]);
  expect(download[0].suggestedFilename()).toBe("guia.txt");
  await context.close();
});

test("director creates a usable teacher account through the user panel", async ({
  page,
  browser,
}) => {
  const email = `docente.nuevo.${Date.now()}@eval.test`;
  const accountName = `Docente de acceso ${Date.now()}`;
  await enter(page, "director@eval.test");
  await page.getByRole("button", { name: "Usuarios", exact: true }).click();
  await page.locator("summary").filter({ hasText: "Crear cuenta" }).click();
  const form = page.locator("form").filter({
    has: page.getByRole("button", {
      name: "Crear cuenta y matrícula",
      exact: true,
    }),
  });
  await form.getByLabel("Nombre", { exact: true }).fill(accountName);
  await form.getByLabel("Correo", { exact: true }).fill(email);
  await form
    .getByLabel("Contraseña inicial", { exact: true })
    .fill("Mercedes2026!");
  await form
    .getByRole("combobox", { name: "Tipo de cuenta", exact: true })
    .selectOption("docente");
  await form
    .getByRole("button", { name: "Crear cuenta y matrícula", exact: true })
    .click();
  await expect(
    page.getByRole("heading", {
      name: accountName,
      exact: true,
    }),
  ).toBeVisible();
  const context = await browser.newContext({
    baseURL: test.info().project.use.baseURL,
  });
  const newcomer = await context.newPage();
  await enter(newcomer, email, false);
  await expect(newcomer.locator(".account")).toContainText(accountName);
  await context.close();
  const account = page.locator("article").filter({
    has: page.getByRole("heading", { name: accountName, exact: true }),
  });
  await account.locator("summary").filter({ hasText: "Editar cuenta" }).click();
  await account
    .getByRole("combobox", { name: "Estado", exact: true })
    .selectOption("0");
  await account
    .getByRole("button", { name: "Guardar cuenta", exact: true })
    .click();
  await expect(account).toHaveCount(0);
});
