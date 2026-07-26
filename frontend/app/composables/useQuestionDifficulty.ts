/**
 * Shared difficulty scale (1-5) matching backend `Question::difficultyLabel()`.
 * Kept as a fixed enum (no CRUD) since the taxonomy is fixed by the assessment design.
 */
export interface DifficultyLevelDef {
  value: number
  labelKey: string
}

export const QUESTION_DIFFICULTY_LEVELS: DifficultyLevelDef[] = [
  { value: 1, labelKey: 'quizBuilder.difficultyLevels.1' },
  { value: 2, labelKey: 'quizBuilder.difficultyLevels.2' },
  { value: 3, labelKey: 'quizBuilder.difficultyLevels.3' },
  { value: 4, labelKey: 'quizBuilder.difficultyLevels.4' },
  { value: 5, labelKey: 'quizBuilder.difficultyLevels.5' },
]

export function useQuestionDifficulty() {
  const { t } = useI18n()

  const options = computed(() => QUESTION_DIFFICULTY_LEVELS.map(level => ({
    label: t(level.labelKey),
    value: level.value,
  })))

  function difficultyLabel(value?: number | null): string {
    if (!value) return '—'
    const level = QUESTION_DIFFICULTY_LEVELS.find(l => l.value === value)
    return level ? t(level.labelKey) : String(value)
  }

  return { levels: QUESTION_DIFFICULTY_LEVELS, options, difficultyLabel }
}
