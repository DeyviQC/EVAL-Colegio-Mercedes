export type Id = string;
export type Role = "director" | "subdirector" | "docente" | "estudiante";
export interface User {
  id: Id;
  name: string;
  email: string;
  role: Role;
}
export interface Period {
  id: Id;
  name: string;
  start_on: string;
  end_on: string;
  state: "planned" | "active" | "closed";
}
export interface Catalog {
  id: Id;
  name: string;
  is_active: boolean;
  grade_id?: Id;
  classification?: "subject" | "area";
}
export interface Assignment {
  id: Id;
  academic_period_id: Id;
  teacher_id: Id;
  instructional_entry_id: Id;
  grade_id: Id;
  section_id: Id;
  state: "planned" | "active" | "closed";
  course: string;
  teacher: string;
  grade: string;
  section: string;
  effective_from: string;
  effective_until: string | null;
  operational_start_key: Id | null;
  operational_end_key: Id | null;
  replaces_assignment_id: Id | null;
}
export interface Enrollment {
  id: Id;
  student_id: Id;
  academic_period_id: Id;
  grade_id: Id;
  section_id: Id;
  student: string;
  grade: string;
  section: string;
  state: "active" | "transferred" | "closed";
  effective_from: string;
  effective_until: string | null;
}
export interface Activity {
  due_date?: string | null;
  availability?: "open" | "closed" | null;
  id: Id;
  teaching_assignment_id: Id;
  title: string;
  description: string;
}
export interface Submission {
  id: Id;
  student_id: Id;
  activity_id: Id;
  teaching_assignment_id: Id;
  accepted_under_enrollment_id: Id;
  accepted_at: string;
  acceptance_operation_key: Id;
  answer?: string;
}
export interface AcademicState {
  user: User;
  current_period: Period | null;
  periods: Period[];
  grades: Catalog[];
  sections: Catalog[];
  entries: Catalog[];
  people: (User & { is_active: boolean })[];
  assignments: Assignment[];
  enrollments: Enrollment[];
  activities: Activity[];
  submissions: Submission[];
}
export interface Material {
  id: Id;
  teaching_assignment_id: Id;
  title: string;
  body: string;
  course: string;
  teacher: string;
  file_name: string | null;
}
export interface Delivery extends Submission {
  title: string;
  course: string;
  student: string;
  grade: "AD" | "A" | "B" | "C" | null;
  feedback: string | null;
  file_name: string | null;
  graded_at: string | null;
}
export interface EducationState {
  materials: Material[];
  deliveries: Delivery[];
  roster: { id: Id; name: string; email: string }[];
  notifications: {
    id: Id;
    message: string;
    page: string;
    is_read: boolean;
    created_at: string;
  }[];
  unread: number;
}
