import { useState, type FormEvent } from "react";
import type { AcademicState, EducationState, Id } from "./contracts";
import { request, session } from "./api";
interface Props {
  tab: string;
  state: AcademicState;
  education: EducationState | null;
  section: Id;
  changed: () => Promise<void>;
}
export default function EducationPanel({
  tab,
  state,
  education,
  section,
  changed,
}: Props) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [userRole, setUserRole] = useState("estudiante"),
    [userGrade, setUserGrade] = useState("");
  const [showInactive, setShowInactive] = useState(false);
  const actor = state.user,
    teacher = actor.role === "docente",
    director = actor.role === "director",
    student = actor.role === "estudiante";
  const assignments = state.assignments.filter(
      (a) => !section || a.section_id === section,
    ),
    active = assignments.filter(
      (a) =>
        a.state === "active" &&
        a.academic_period_id === state.current_period?.id,
    );
  const ids = new Set(assignments.map((a) => a.id));
  async function execute(
    action: string,
    data: Record<string, unknown> | FormData,
  ) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await request(`education/${action}`, data);
      await session();
      await changed();
      setNotice("Los cambios se guardaron correctamente.");
    } catch (problem) {
      setError((problem as Error).message);
    } finally {
      setBusy(false);
    }
  }
  function form(action: string, files = false) {
    return (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      void execute(action, files ? data : Object.fromEntries(data));
    };
  }
  if (!education) return <p>Cargando datos…</p>;
  const rows = education.deliveries.filter(
    (row) => !section || ids.has(row.teaching_assignment_id),
  );
  const courses = (
    <label>
      Curso
      <select name="assignment_id" required>
        {active.map((a) => (
          <option value={a.id} key={a.id}>
            {a.course} · {a.section}
          </option>
        ))}
      </select>
    </label>
  );
  const attachment = (
    <label>
      Adjunto · PDF, imagen, TXT o DOCX · máximo 10 MB
      <input
        type="file"
        name="attachment"
        accept=".pdf,.jpg,.jpeg,.png,.txt,.docx"
      />
    </label>
  );
  return (
    <section>
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
      {["materials", "library"].includes(tab) && (
        <>
          {teacher && section && (
            <details className="panel">
              <summary>Publicar material</summary>
              <form onSubmit={form("publish_material", true)}>
                {courses}
                <label>
                  Título
                  <input name="title" required maxLength={200} />
                </label>
                <label>
                  Contenido
                  <textarea name="body" maxLength={10000} />
                </label>
                {attachment}
                <p className="subtle">
                  Se publica directamente para las estudiantes del salón.
                </p>
                <button disabled={busy}>Publicar material</button>
              </form>
            </details>
          )}
          {!section && !student ? (
            <p className="empty">Selecciona un salón.</p>
          ) : (
            education.materials
              .filter((m) => !section || ids.has(m.teaching_assignment_id))
              .map((m) => (
                <article className="panel" key={m.id}>
                  <span className="badge">{m.course}</span>
                  <h3>{m.title}</h3>
                  <p>{m.body}</p>
                  <small>{m.teacher}</small>
                  {m.file_name && (
                    <p>
                      <a
                        className="download"
                        href={`/api/education/files/material/${m.id}`}
                      >
                        Descargar {m.file_name}
                      </a>
                    </p>
                  )}
                  {teacher &&
                    assignments.some(
                      (a) =>
                        a.id === m.teaching_assignment_id &&
                        a.state === "active" &&
                        a.academic_period_id === state.current_period?.id,
                    ) && (
                      <details>
                        <summary>Editar contenido</summary>
                        <form onSubmit={form("edit_material")}>
                          <input type="hidden" name="id" value={m.id} />
                          <label>
                            Título
                            <input
                              name="title"
                              defaultValue={m.title}
                              required
                            />
                          </label>
                          <label>
                            Contenido
                            <textarea name="body" defaultValue={m.body} />
                          </label>
                          <button disabled={busy}>Guardar cambios</button>
                        </form>
                      </details>
                    )}
                  {(teacher || director) &&
                    assignments.some(
                      (a) =>
                        a.id === m.teaching_assignment_id &&
                        a.academic_period_id === state.current_period?.id,
                    ) && (
                      <button
                        className="danger"
                        disabled={busy}
                        onClick={() => {
                          if (confirm("¿Eliminar este material y su archivo?"))
                            void execute("delete_material", { id: m.id });
                        }}
                      >
                        Eliminar material
                      </button>
                    )}
                </article>
              ))
          )}
          {education.materials.length === 0 && (
            <p className="empty">No hay materiales publicados en este salón.</p>
          )}
        </>
      )}
      {["submissions", "grades", "reports"].includes(tab) && (
        <>
          {education.roster.length > 0 && (
            <details className="panel" open={tab === "reports"}>
              <summary>
                Estudiantes del salón · {education.roster.length}
              </summary>
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Estudiante</th>
                      <th>Entregas</th>
                      <th>Calificadas</th>
                    </tr>
                  </thead>
                  <tbody>
                    {education.roster.map((p) => (
                      <tr key={p.id}>
                        <td>{p.name}</td>
                        <td>
                          {rows.filter((r) => r.student_id === p.id).length}
                        </td>
                        <td>
                          {
                            rows.filter((r) => r.student_id === p.id && r.grade)
                              .length
                          }
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </details>
          )}
          {tab === "reports" && (
            <div className="cards">
              <article>
                <h3>{rows.length} entregas</h3>
                <p>
                  {rows.filter((r) => !r.grade).length} pendientes ·{" "}
                  {rows.filter((r) => r.grade).length} calificadas
                </p>
              </article>
              {["AD", "A", "B", "C"].map((g) => (
                <article key={g}>
                  <h3>{g}</h3>
                  <p>
                    {rows.filter((r) => r.grade === g).length} calificaciones
                  </p>
                </article>
              ))}
            </div>
          )}
          {!section && !student ? (
            <p className="empty">Selecciona un salón.</p>
          ) : (
            rows.map((r) => (
              <article className="panel" key={r.id}>
                <span className="badge">
                  {r.grade ? `Nota ${r.grade}` : "Sin calificar"}
                </span>
                <h3>{r.title}</h3>
                <p>
                  <strong>{r.student}</strong> · {r.course}
                </p>
                <p>{r.answer}</p>
                {r.file_name && (
                  <p>
                    <a
                      className="download"
                      href={`/api/education/files/submission/${r.id}`}
                    >
                      Descargar {r.file_name}
                    </a>
                  </p>
                )}
                {r.feedback && (
                  <p>
                    <strong>Comentarios del docente:</strong> {r.feedback}
                  </p>
                )}
                {teacher &&
                  assignments.find((a) => a.id === r.teaching_assignment_id)
                    ?.academic_period_id === state.current_period?.id && (
                    <form onSubmit={form("assess")}>
                      <input type="hidden" name="submission_id" value={r.id} />
                      <label>
                        Calificación
                        <select name="grade" defaultValue={r.grade ?? "A"}>
                          {["AD", "A", "B", "C"].map((g) => (
                            <option key={g}>{g}</option>
                          ))}
                        </select>
                      </label>
                      <label>
                        Retroalimentación
                        <textarea
                          name="feedback"
                          defaultValue={r.feedback ?? ""}
                          required
                          maxLength={5000}
                        />
                      </label>
                      <button disabled={busy}>Guardar calificación</button>
                    </form>
                  )}
                {student &&
                  !r.grade &&
                  state.activities.find((a) => a.id === r.activity_id)
                    ?.availability !== "closed" &&
                  state.enrollments.some(
                    (e) =>
                      e.id === r.accepted_under_enrollment_id &&
                      e.state === "active" &&
                      e.academic_period_id === state.current_period?.id,
                  ) && (
                    <details>
                      <summary>Editar mi entrega</summary>
                      <form onSubmit={form("submit", true)}>
                        <input
                          type="hidden"
                          name="activity_id"
                          value={r.activity_id}
                        />
                        <label>
                          Respuesta
                          <textarea name="answer" defaultValue={r.answer} />
                        </label>
                        {attachment}
                        <button disabled={busy}>Actualizar entrega</button>
                      </form>
                    </details>
                  )}
              </article>
            ))
          )}
          {rows.length === 0 && (
            <p className="empty">Todavía no hay entregas.</p>
          )}
        </>
      )}
      {tab === "users" && director && (
        <>
          <details className="panel">
            <summary>Crear cuenta</summary>
            <form onSubmit={form("create_user")}>
              <label>
                Nombre
                <input name="name" required maxLength={120} />
              </label>
              <label>
                Correo
                <input type="email" name="email" required />
              </label>
              <label>
                Contraseña inicial
                <input
                  type="password"
                  name="password"
                  required
                  minLength={10}
                />
              </label>
              <label>
                Tipo de cuenta
                <select
                  name="role"
                  value={userRole}
                  onChange={(e) => setUserRole(e.target.value)}
                >
                  <option value="estudiante">Estudiante</option>
                  <option value="docente">Docente</option>
                  <option value="subdirector">Subdirección</option>
                  <option value="director">Dirección</option>
                </select>
              </label>
              {userRole === "estudiante" && (
                <>
                  <label>
                    Período
                    <select name="academic_period_id" required>
                      {state.periods
                        .filter((p) => p.state === "active")
                        .map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.name}
                          </option>
                        ))}
                    </select>
                  </label>
                  <label>
                    Grado
                    <select
                      name="grade_id"
                      value={userGrade || state.grades[0]?.id}
                      onChange={(e) => setUserGrade(e.target.value)}
                    >
                      {state.grades
                        .filter((g) => g.is_active)
                        .map((g) => (
                          <option key={g.id} value={g.id}>
                            {g.name}
                          </option>
                        ))}
                    </select>
                  </label>
                  <label>
                    Salón
                    <select name="section_id">
                      {state.sections
                        .filter(
                          (s) =>
                            s.is_active &&
                            s.grade_id === (userGrade || state.grades[0]?.id),
                        )
                        .map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.name}
                          </option>
                        ))}
                    </select>
                  </label>
                </>
              )}
              <button disabled={busy}>Crear cuenta y matrícula</button>
            </form>
          </details>
          <p className="subtle">
            Se muestran el equipo docente/directivo y las alumnas del salón
            seleccionado. El rol y el historial se conservan.
          </p>
          <div className="cards">
            <label>
              <input
                type="checkbox"
                checked={showInactive}
                onChange={(event) => setShowInactive(event.target.checked)}
              />
              Mostrar cuentas desactivadas
            </label>
            {state.people
              .filter((p) => p.is_active || showInactive)
              .map((p) => (
                <article key={p.id}>
                  <h3>{p.name}</h3>
                  <span className="badge">{p.role}</span>
                  <details>
                    <summary>Editar cuenta</summary>
                    <form
                      onSubmit={(event) => {
                        event.preventDefault();
                        const d = new FormData(event.currentTarget);
                        void execute("update_user", {
                          id: p.id,
                          name: d.get("name"),
                          email: d.get("email"),
                          is_active: d.get("is_active") === "1",
                          ...(d.get("password")
                            ? { password: d.get("password") }
                            : {}),
                        });
                      }}
                    >
                      <label>
                        Nombre
                        <input name="name" defaultValue={p.name} required />
                      </label>
                      <label>
                        Correo
                        <input
                          name="email"
                          type="email"
                          defaultValue={p.email}
                          required
                        />
                      </label>
                      <label>
                        Estado
                        <select
                          name="is_active"
                          defaultValue={p.is_active ? "1" : "0"}
                        >
                          <option value="1">Activa</option>
                          <option value="0">Desactivada</option>
                        </select>
                      </label>
                      <label>
                        Restablecer contraseña (opcional)
                        <input name="password" type="password" minLength={10} />
                      </label>
                      <button disabled={busy}>Guardar cuenta</button>
                    </form>
                  </details>
                </article>
              ))}
          </div>
        </>
      )}
      {tab === "notifications" && (
        <>
          <button
            disabled={busy}
            onClick={() => void execute("read_notifications", {})}
          >
            Marcar todo como leído
          </button>
          {education.notifications.map((n) => (
            <article className="panel" key={n.id}>
              <span className="badge">{n.is_read ? "Leído" : "Nuevo"}</span>
              <p>{n.message}</p>
              <small>{n.created_at}</small>
            </article>
          ))}
          {education.notifications.length === 0 && (
            <p>No tienes notificaciones.</p>
          )}
        </>
      )}
      {tab === "profile" && (
        <article className="panel">
          <h3>{actor.name}</h3>
          <p>{actor.email}</p>
          <form onSubmit={form("change_password")}>
            <label>
              Contraseña actual
              <input
                name="current_password"
                type="password"
                required
                autoComplete="current-password"
              />
            </label>
            <label>
              Nueva contraseña
              <input
                name="password"
                type="password"
                required
                minLength={10}
                autoComplete="new-password"
              />
            </label>
            <button disabled={busy}>Cambiar contraseña</button>
          </form>
        </article>
      )}
    </section>
  );
}
