import { useEffect, useState, type FormEvent } from "react";
import { request, session } from "./api";
import EducationPanel from "./EducationPanel";
import type { AcademicState, EducationState, Id } from "./contracts";
import "./App.css";
const roles = {
  director: "Dirección",
  subdirector: "Subdirección",
  docente: "Docente",
  estudiante: "Estudiante",
};
const states = {
  planned: "Planificado",
  active: "Activo",
  closed: "Cerrado",
  transferred: "Trasladada",
};
const options = (rows: { id: Id; name: string }[]) =>
  rows.map((row) => (
    <option key={row.id} value={row.id}>
      {row.name}
    </option>
  ));
function Field({
  name,
  label,
  type = "text",
}: {
  name: string;
  label: string;
  type?: string;
}) {
  return (
    <label>
      {label}
      <input name={name} type={type} required />
    </label>
  );
}
export default function App() {
  const [state, setState] = useState<AcademicState | null>(null);
  const [education, setEducation] = useState<EducationState | null>(null);
  const [tab, setTab] = useState("assignments");
  const [grade, setGrade] = useState<Id>("");
  const [section, setSection] = useState<Id>("");
  const [formGrade, setFormGrade] = useState<Id>("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  async function refresh(room = section) {
    const data = await request<AcademicState>(
      `academic${(state?.user.role === "subdirector" || state?.user.role === "director") && room ? `?section_id=${room}` : ""}`,
    );
    const extra = await request<EducationState>(
      `education${room && ["director", "docente"].includes(data.user.role) ? `?section_id=${room}` : ""}`,
    );
    setState(data);
    setEducation(extra);
  }
  useEffect(() => {
    void session()
      .then(() => request<AcademicState>("academic"))
      .then(async (data) => {
        const extra = await request<EducationState>("education");
        setState(data);
        setEducation(extra);
      })
      .catch(() => setState(null))
      .finally(() => setLoading(false));
  }, []);
  const pollingUser = state?.user.id;
  const pollingRole = state?.user.role;
  useEffect(() => {
    if (!pollingUser || !pollingRole) return;
    let cancelled = false;
    const timer = window.setInterval(async () => {
      if (document.visibilityState !== "visible") return;
      try {
        const data = await request<AcademicState>(
          `academic${["director", "subdirector"].includes(pollingRole) && section ? `?section_id=${section}` : ""}`,
        );
        const extra = await request<EducationState>(
          `education${["director", "docente"].includes(pollingRole) && section ? `?section_id=${section}` : ""}`,
        );
        if (!cancelled) {
          setState(data);
          setEducation(extra);
        }
      } catch {
        /* Read polling never retries a write or clears an in-progress form. */
      }
    }, 15000);
    return () => {
      cancelled = true;
      window.clearInterval(timer);
    };
  }, [pollingUser, pollingRole, section]);
  async function login(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const credentials = Object.fromEntries(new FormData(event.currentTarget));
    setError("");
    setBusy(true);
    try {
      await session();
      await request("login", credentials);
      await session();
      await refresh("");
    } catch (problem) {
      setError((problem as Error).message);
    } finally {
      setBusy(false);
    }
  }
  async function command(
    action: string,
    values: Record<string, unknown> | FormData,
  ) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await request(
        action === "accept_submission"
          ? "education/submit"
          : `academic/${action}`,
        values,
      );
      await refresh();
      setNotice("La operación se guardó con su contexto académico.");
    } catch (problem) {
      setError((problem as Error).message);
    } finally {
      setBusy(false);
    }
  }
  function submit(action: string, files = false) {
    return (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const payload = new FormData(event.currentTarget);
      const values = files ? payload : Object.fromEntries(payload);
      void command(action, values);
    };
  }
  if (loading) return <main className="login-card">Cargando EVAL…</main>;
  if (!state)
    return (
      <main className="login-card">
        <span className="eyebrow">NUESTRA SEÑORA DE LAS MERCEDES</span>
        <div className="brand">
          <img
            className="crest"
            src="/client/escudo.png"
            alt="Escudo de Nuestra Señora de las Mercedes"
          />
          <h1>EVAL</h1>
        </div>
        <h2>Inicia sesión en tu comunidad educativa</h2>
        <form onSubmit={login}>
          <Field name="email" label="Correo electrónico" type="email" />
          <Field name="password" label="Contraseña" type="password" />
          <button disabled={busy}>Ingresar →</button>
        </form>
        {error && (
          <p role="alert" className="error">
            {error}
          </p>
        )}
        <details>
          <summary>Cuentas de prueba</summary>
          <p>
            director@eval.test, subdirector@eval.test, docente@eval.test,
            estudiante@eval.test
          </p>
          <p>Contraseña inicial: Mercedes2026!</p>
        </details>
      </main>
    );
  const user = state.user;
  const director = user.role === "director";
  const manager = director || user.role === "subdirector";
  const teacher = user.role === "docente";
  const tabs = manager
    ? [
        ["assignments", "Asignaciones"],
        ...(director
          ? [
              ["periods", "Períodos"],
              ["catalog", "Catálogo"],
              ["enrollments", "Matrículas"],
            ]
          : []),
        ["history", "Historial"],
      ]
    : [
        ["assignments", "Mis cursos"],
        ["activities", "Actividades"],
        ["submissions", "Entregas"],
        ["history", "Mi historial"],
      ];
  tabs.push(
    [
      "notifications",
      `Notificaciones${education?.unread ? ` (${education.unread})` : ""}`,
    ],
    ["profile", "Mi cuenta"],
  );
  if (director)
    tabs.push(
      ["users", "Usuarios"],
      ["materials", "Materiales"],
      ["reports", "Reportes"],
    );
  if (teacher)
    tabs.push(["materials", "Materiales"], ["library", "Biblioteca"]);
  if (user.role === "estudiante")
    tabs.push(["library", "Biblioteca"], ["grades", "Mis notas"]);
  const grades = director
    ? state.grades.map((row) => ({ id: row.id, name: row.name }))
    : [
        ...new Map(
          state.assignments.map((row) => [
            row.grade_id,
            { id: row.grade_id, name: row.grade },
          ]),
        ).values(),
      ];
  const rooms = director
    ? state.sections
        .filter((row) => row.grade_id === grade)
        .map((row) => ({ id: row.id, name: row.name }))
    : [
        ...new Map(
          state.assignments
            .filter((row) => row.grade_id === grade)
            .map((row) => [
              row.section_id,
              { id: row.section_id, name: row.section },
            ]),
        ).values(),
      ];
  const visible = state.assignments.filter(
    (row) =>
      (!grade || row.grade_id === grade) &&
      (!section || row.section_id === section),
  );
  const roomAssignments = section ? visible : [];
  const assignmentIds = new Set(roomAssignments.map((row) => row.id));
  const activeIds = new Set(
    roomAssignments
      .filter(
        (row) =>
          row.state === "active" &&
          row.academic_period_id === state.current_period?.id,
      )
      .map((row) => row.id),
  );
  const activeSections = state.sections.filter((row) => row.is_active);
  const teachers = state.people.filter(
    (row) => row.role === "docente" && row.is_active,
  );
  const periodField = (
    <label>
      Período
      <select name="academic_period_id" required>
        {options(state.periods.filter((row) => row.state === "active"))}
      </select>
    </label>
  );
  const chosenGrade = formGrade || grade || state.grades[0]?.id;
  const gradeField = (
    <label>
      Grado
      <select
        name="grade_id"
        required
        value={chosenGrade}
        onChange={(event) => setFormGrade(event.target.value)}
      >
        {options(state.grades.filter((row) => row.is_active))}
      </select>
    </label>
  );
  const sectionField = (
    <label>
      Sección
      <select name="section_id" required>
        {options(
          activeSections
            .filter((row) => row.grade_id === chosenGrade)
            .map((row) => ({
              id: row.id,
              name: `${state.grades.find((g) => g.id === row.grade_id)?.name ?? ""} · ${row.name}`,
            })),
        )}
      </select>
    </label>
  );
  return (
    <div className="layout">
      <aside>
        <h1>
          EVAL<span>AULA ACADÉMICA</span>
        </h1>
        <p>Nuestra Señora de las Mercedes</p>
        <nav>
          {tabs.map(([key, label]) => (
            <button
              className={tab === key ? "active" : ""}
              key={key}
              onClick={() => setTab(key)}
            >
              {label}
            </button>
          ))}
        </nav>
        <div className="account">
          <strong>{user.name}</strong>
          <small>{roles[user.role]}</small>
          <button
            onClick={() =>
              void request("logout", {})
                .then(() => {
                  setState(null);
                  setGrade("");
                  setSection("");
                  setTab("assignments");
                })
                .catch((problem) => setError(problem.message))
            }
          >
            Cerrar sesión
          </button>
        </div>
      </aside>
      <main>
        <header>
          <div>
            <span className="eyebrow">GESTIÓN ACADÉMICA</span>
            <h2>{tabs.find(([key]) => key === tab)?.[1]}</h2>
          </div>
          <span className="badge">
            {state.current_period?.name ??
              (user.role === "subdirector" && !section
                ? "Selecciona un salón"
                : "Sin período actual")}
          </span>
        </header>
        <p className="subtle">
          Selecciona tu grado y salón para trabajar con sus cursos y consultar
          el historial.
        </p>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {notice && (
          <p className="success" role="status">
            {notice}
          </p>
        )}
        {[
          "assignments",
          "activities",
          "submissions",
          "enrollments",
          "history",
        ].includes(tab) && (
          <section className="scope">
            <label>
              Grado
              <select
                value={grade}
                onChange={(event) => {
                  setGrade(event.target.value);
                  setSection("");
                }}
              >
                <option value="">Elige un grado</option>
                {options(grades)}
              </select>
            </label>
            <label>
              Salón
              <select
                value={section}
                disabled={!grade}
                onChange={(event) => {
                  setSection(event.target.value);
                  void refresh(event.target.value).catch((problem) =>
                    setError(problem.message),
                  );
                }}
              >
                <option value="">Elige una sección</option>
                {options(rooms)}
              </select>
            </label>
            <button
              onClick={() =>
                void refresh().catch((problem) => setError(problem.message))
              }
            >
              Actualizar
            </button>
          </section>
        )}
        {tab === "assignments" && (
          <>
            {!section ? (
              <div className="empty">
                Selecciona un grado y un salón para ver las asignaciones.
              </div>
            ) : (
              <>
                <div className="cards">
                  {roomAssignments.map((row) => (
                    <article key={row.id}>
                      <span className="badge">{states[row.state]}</span>
                      <h3>{row.course}</h3>
                      <p>{row.teacher}</p>
                      <small>
                        {row.grade} · {row.section}
                        <br />
                        Asignación #{row.id}
                      </small>
                      {manager && (
                        <div className="actions">
                          {row.state === "planned" && (
                            <button
                              disabled={busy}
                              onClick={() =>
                                void command("activate_assignment", {
                                  id: row.id,
                                })
                              }
                            >
                              Activar
                            </button>
                          )}
                          {row.state === "active" && (
                            <>
                              <button
                                disabled={busy}
                                onClick={() =>
                                  void command("close_assignment", {
                                    id: row.id,
                                  })
                                }
                              >
                                Cerrar asignación
                              </button>
                              <details>
                                <summary>Reemplazar docente</summary>
                                <form onSubmit={submit("replace_teacher")}>
                                  <input
                                    type="hidden"
                                    name="id"
                                    value={row.id}
                                  />
                                  <label>
                                    Nuevo docente
                                    <select name="teacher_id">
                                      {options(teachers)}
                                    </select>
                                  </label>
                                  <button disabled={busy}>
                                    Confirmar reemplazo inmediato
                                  </button>
                                </form>
                              </details>
                            </>
                          )}
                        </div>
                      )}
                    </article>
                  ))}
                </div>
                {manager && (
                  <details className="panel">
                    <summary>Crear una asignación</summary>
                    <form onSubmit={submit("create_assignment")}>
                      {periodField}
                      {gradeField}
                      {sectionField}
                      <label>
                        Área
                        <select name="instructional_entry_id">
                          {options(
                            state.entries.filter((row) => row.is_active),
                          )}
                        </select>
                      </label>
                      <label>
                        Docente
                        <select name="teacher_id">{options(teachers)}</select>
                      </label>
                      <button disabled={busy}>Crear planificada</button>
                    </form>
                  </details>
                )}
              </>
            )}
          </>
        )}
        {tab === "periods" && director && (
          <>
            <details className="panel">
              <summary>Crear período</summary>
              <form onSubmit={submit("create_period")}>
                <Field name="name" label="Nombre" />
                <Field name="start_on" label="Inicio" type="date" />
                <Field name="end_on" label="Fin" type="date" />
                <button disabled={busy}>Guardar planificado</button>
              </form>
            </details>
            <div className="cards">
              {state.periods.map((row) => (
                <article key={row.id}>
                  <span className="badge">{states[row.state]}</span>
                  <h3>{row.name}</h3>
                  <p>
                    {row.start_on} → {row.end_on}
                  </p>
                  {row.state !== "closed" && (
                    <button
                      disabled={busy}
                      onClick={() =>
                        void command(
                          row.state === "planned"
                            ? "activate_period"
                            : "close_period",
                          { id: row.id },
                        )
                      }
                    >
                      {row.state === "planned" ? "Activar" : "Cerrar período"}
                    </button>
                  )}
                </article>
              ))}
            </div>
          </>
        )}
        {tab === "catalog" && director && (
          <>
            <details className="panel">
              <summary>Crear catálogo</summary>
              <form onSubmit={submit("create_catalog")}>
                <label>
                  Tipo
                  <select name="type">
                    <option value="entry">Área o curso</option>
                    <option value="grade">Grado</option>
                    <option value="section">Sección</option>
                  </select>
                </label>
                <Field name="name" label="Nombre" />
                <label>
                  Clasificación (para área/curso)
                  <select name="classification">
                    <option value="area">Área</option>
                    <option value="subject">Curso</option>
                  </select>
                </label>
                {gradeField}
                <button disabled={busy}>Guardar catálogo</button>
              </form>
            </details>
            {[
              ["entry", state.entries],
              ["grade", state.grades],
              ["section", state.sections],
            ].map(([kind, rows]) => (
              <section key={kind as string}>
                <h3>
                  {kind === "entry"
                    ? "Áreas y cursos"
                    : kind === "grade"
                      ? "Grados"
                      : "Secciones"}
                </h3>
                <div className="cards">
                  {(rows as typeof state.entries).map((row) => (
                    <article key={row.id}>
                      <form
                        onSubmit={(event) => {
                          event.preventDefault();
                          const data = new FormData(event.currentTarget);
                          void command("update_catalog", {
                            type: kind,
                            id: row.id,
                            name: data.get("name"),
                            is_active: data.get("is_active") === "1",
                          });
                        }}
                      >
                        <label>
                          Nombre
                          <input name="name" defaultValue={row.name} required />
                        </label>
                        <label>
                          Estado
                          <select
                            name="is_active"
                            defaultValue={row.is_active ? "1" : "0"}
                          >
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                          </select>
                        </label>
                        <button disabled={busy}>
                          Actualizar conservando identidad
                        </button>
                      </form>
                    </article>
                  ))}
                </div>
              </section>
            ))}
          </>
        )}
        {tab === "enrollments" && director && (
          <>
            <details className="panel">
              <summary>Matricular estudiante</summary>
              <form onSubmit={submit("enroll")}>
                <label>
                  Estudiante
                  <select name="student_id">
                    {options(
                      state.people.filter((row) => row.role === "estudiante"),
                    )}
                  </select>
                </label>
                {periodField}
                {gradeField}
                {sectionField}
                <button disabled={busy}>Crear matrícula inmediata</button>
              </form>
            </details>

            {section &&
              state.enrollments
                .filter((row) => row.section_id === section)
                .map((row) => (
                  <article className="panel" key={row.id}>
                    <span className="badge">{states[row.state]}</span>
                    <h3>{row.student}</h3>
                    <p>
                      {row.grade} · {row.section} · Matrícula #{row.id}
                    </p>
                    {row.state === "active" && (
                      <details>
                        <summary>Trasladar a otro salón</summary>
                        <form onSubmit={submit("transfer")}>
                          <input type="hidden" name="id" value={row.id} />
                          {gradeField}
                          {sectionField}
                          <button disabled={busy}>
                            Confirmar traslado inmediato
                          </button>
                        </form>
                      </details>
                    )}
                  </article>
                ))}
          </>
        )}
        {tab === "activities" && (
          <>
            {teacher && section && (
              <details className="panel">
                <summary>Crear actividad</summary>
                <form onSubmit={submit("create_activity")}>
                  <label>
                    Asignación activa
                    <select name="assignment_id">
                      {options(
                        roomAssignments
                          .filter((row) => activeIds.has(row.id))
                          .map((row) => ({ id: row.id, name: row.course })),
                      )}
                    </select>
                  </label>
                  <Field name="title" label="Título" />
                  <label>
                    Fecha de entrega (opcional)
                    <input name="due_date" type="date" />
                  </label>
                  <label>
                    Instrucciones
                    <textarea name="description" required />
                  </label>
                  <button disabled={busy}>Publicar actividad</button>
                </form>
              </details>
            )}
            {!section ? (
              <div className="empty">Selecciona un salón.</div>
            ) : (
              state.activities
                .filter((row) => assignmentIds.has(row.teaching_assignment_id))
                .map((row) => (
                  <article className="panel" key={row.id}>
                    <h3>{row.title}</h3>
                    <span className="badge">
                      {row.availability === "closed" ? "Cerrada" : "Abierta"}
                    </span>
                    <p>{row.description}</p>
                    <p>
                      {row.due_date
                        ? `Entrega hasta ${row.due_date}`
                        : "Sin fecha límite"}
                    </p>
                    {teacher && row.availability !== "closed" && (
                      <button
                        disabled={busy}
                        onClick={() => {
                          if (
                            confirm(
                              "¿Cerrar esta actividad para nuevas entregas?",
                            )
                          )
                            void request("education/close_activity", {
                              id: row.id,
                            })
                              .then(() => refresh())
                              .catch((problem) => setError(problem.message));
                        }}
                      >
                        Cerrar actividad
                      </button>
                    )}
                    <small>
                      Asignación original #{row.teaching_assignment_id}
                    </small>
                    {user.role === "estudiante" &&
                      row.availability !== "closed" &&
                      !state.submissions.some(
                        (s) => s.activity_id === row.id,
                      ) && (
                        <form onSubmit={submit("accept_submission", true)}>
                          <input
                            type="hidden"
                            name="activity_id"
                            value={row.id}
                          />
                          <label>
                            Respuesta
                            <textarea name="answer" />
                          </label>
                          <label>
                            Adjuntar evidencia
                            <input
                              type="file"
                              name="attachment"
                              accept=".pdf,.jpg,.jpeg,.png,.txt,.docx"
                            />
                          </label>
                          <button disabled={busy}>
                            Enviar al docente original
                          </button>
                        </form>
                      )}
                  </article>
                ))
            )}
          </>
        )}
        {[
          "materials",
          "library",
          "submissions",
          "grades",
          "users",
          "reports",
          "notifications",
          "profile",
        ].includes(tab) && (
          <EducationPanel
            tab={tab}
            state={state}
            education={education}
            section={section}
            changed={() => refresh()}
          />
        )}
        {tab === "history" && (
          <>
            {state.enrollments.length > 0 && (
              <>
                <h3>Historial de matrículas</h3>
                <div className="cards">
                  {state.enrollments
                    .filter(
                      (row) =>
                        user.role !== "director" || row.section_id === section,
                    )
                    .map((row) => (
                      <article key={row.id}>
                        <h3>
                          {row.grade} · {row.section}
                        </h3>
                        <span className="badge">{states[row.state]}</span>
                        <p>
                          #{row.id} · {row.effective_from} →{" "}
                          {row.effective_until ?? "Actual"}
                        </p>
                      </article>
                    ))}
                </div>
              </>
            )}
            <h3>Historial de asignaciones</h3>
            <div className="cards">
              {state.assignments
                .filter(
                  (row) =>
                    row.state === "closed" &&
                    (!section || row.section_id === section),
                )
                .map((row) => (
                  <article key={row.id}>
                    <h3>{row.course}</h3>
                    <p>
                      {row.teacher} · {row.grade} · {row.section}
                    </p>
                    <small>
                      #{row.id} · Orden {row.operational_start_key} →{" "}
                      {row.operational_end_key}
                    </small>
                  </article>
                ))}
            </div>
            <p className="subtle">
              El historial es de consulta; no autoriza nuevas actividades ni
              cambia el destinatario de entregas anteriores.
            </p>
          </>
        )}
      </main>
    </div>
  );
}
