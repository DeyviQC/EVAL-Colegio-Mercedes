# UI Mapping: Accepted Activities and Deliveries Screens

The human explicitly selected the collaborator's frontend presented to the professor. Source: 2e5d053, frontend/src/App.tsx (Activities) and frontend/src/EducationPanel.tsx (Deliveries). This is an implementation mapping for those accepted screens, not a new standalone preview.

## Activities

Keep the grade/section/course selection and activity cards. Teacher creation includes active assignment, title, optional delivery date and instructions, with Publish activity. Each card shows title, instructions, Open/Closed, optional date and original course. The original teacher has Close activity with an explicit confirmation.

Eligible students see Answer and Attach evidence, then Submit. Display the confirmed result and disable duplicate first submission once accepted. Existing deliveries link to Deliveries for consultation and authorized updates. The date text explains that submissions remain available until explicit closure while the period is active; do not promise automatic deadline enforcement.

## Deliveries

Teacher cards show activity, student name, answer, original course and protected evidence download. Student cards show only their own work and an Update my delivery form when currently authorized. Add a compact version-history disclosure with confirmed timestamps and retained evidence. Keep assessment/feedback controls for the later grading unit; do not render a working-looking save-grade button before its server behavior exists.

Show missing, empty, loading, denied and unavailable results clearly. Inputs remain labeled and keyboard accessible. Mutation uncertainty is distinct from confirmed success with a failed list refresh. Verify 768 px tablet layout and the complete live teacher/student journey.
