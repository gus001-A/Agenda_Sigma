<?php

namespace Database\Seeders;

/** Plantillas iniciales de formatos clínicos (ilustrativas; la Clínica define los definitivos). */
class PlantillasFormatos
{
    public const ANTECEDENTES = ['Diabetes', 'Hipertensión', 'Cáncer', 'Cardiopatía', 'Asma', 'Enfermedad de tiroides', 'Ninguno'];

    public static function campo(string $id, string $label, string $type, string $mode = 'optional', array $options = [], array $extra = []): array
    {
        return ['id' => $id, 'label' => $label, 'type' => $type, 'mode' => $mode, 'options' => $options] + $extra;
    }

    public static function datos(): array
    {
        return ['title' => 'Sus datos', 'fields' => [
            self::campo('nombre', 'Nombre completo', 'text', 'readonly', [], ['prefill' => 'name']),
            self::campo('nac', 'Fecha de nacimiento', 'date', 'readonly', [], ['prefill' => 'birth']),
            self::campo('tel', 'Teléfono de contacto', 'text', 'required', [], ['prefill' => 'phone']),
        ]];
    }

    public static function motivo(): array
    {
        return ['title' => 'Motivo de consulta', 'fields' => [
            self::campo('motivo', 'Motivo de consulta', 'textarea', 'required'),
            self::campo('padec', 'Padecimiento actual: ¿qué siente y desde cuándo?', 'textarea', 'required'),
        ]];
    }

    public static function medAlergias(): array
    {
        return ['title' => 'Medicamentos y alergias', 'fields' => [
            self::campo('meds', 'Medicamentos que toma actualmente', 'meds'),
            self::campo('alerg', 'Alergias', 'allergies', 'required'),
        ]];
    }

    public static function estudios(): array
    {
        return ['title' => 'Estudios previos', 'fields' => [
            self::campo('estudios', '¿Cuenta con estudios previos (laboratorio, radiografías, resonancias)?', 'yesno'),
            self::campo('archivos', 'Adjunte sus estudios', 'files'),
        ]];
    }

    public static function consentimiento(): array
    {
        return ['title' => 'Consentimiento', 'fields' => [
            self::campo('privacidad', 'Aviso de privacidad y uso de datos clínicos', 'consent', 'required'),
            self::campo('firma', 'Firma del paciente', 'signature'),
        ]];
    }

    public static function antecedentes(): array
    {
        return ['title' => 'Antecedentes', 'fields' => [
            self::campo('heredo', 'Antecedentes heredofamiliares', 'history', 'optional', self::ANTECEDENTES),
            self::campo('perso', 'Antecedentes personales', 'history', 'optional', self::ANTECEDENTES),
            self::campo('quir', 'Antecedentes quirúrgicos (cirugías previas)', 'textarea'),
        ]];
    }

    /** @return array<string, array{name:string, spec:?string, type:string, versions:array}> */
    public static function todas(): array
    {
        $dolor11 = ['title' => 'Valoración del dolor', 'fields' => [
            self::campo('zona', 'Zona del dolor', 'select', 'required', ['Rodilla', 'Hombro', 'Columna lumbar', 'Cadera', 'Tobillo', 'Muñeca', 'Otra']),
            self::campo('evol', 'Tiempo de evolución', 'single', 'required', ['Menos de 1 semana', '1 a 4 semanas', '1 a 6 meses', 'Más de 6 meses']),
            self::campo('dolor', 'Escala del dolor (0 sin dolor, 10 máximo)', 'scale', 'required'),
            self::campo('lesiones', '¿Ha tenido lesiones previas en la zona?', 'yesno'),
            self::campo('trat', 'Tratamientos anteriores', 'textarea'),
        ]];
        $dolor10 = $dolor11;
        $dolor10['fields'] = array_values(array_filter($dolor10['fields'], fn ($f) => ! in_array($f['id'], ['dolor', 'trat'], true)));
        $tr11 = [self::datos(), self::motivo(), $dolor11, self::antecedentes(), self::medAlergias(), self::estudios(), self::consentimiento()];
        $tr10 = [self::datos(), self::motivo(), $dolor10, self::antecedentes(), self::medAlergias(), self::estudios(), self::consentimiento()];

        return [
            'f1' => ['name' => 'Historia clínica de Traumatología', 'spec' => 'e1', 'type' => 'Primera consulta', 'versions' => [
                ['v' => '1.0', 'date' => '2026-03-02', 'notes' => 'Versión inicial aprobada por la Clínica', 'sections' => $tr10],
                ['v' => '1.1', 'date' => '2026-08-18', 'notes' => 'Se agregaron escala del dolor y tratamientos anteriores', 'sections' => $tr11],
            ]],
            'f2' => ['name' => 'Seguimiento de Traumatología', 'spec' => 'e1', 'type' => 'Consulta subsecuente', 'versions' => [
                ['v' => '1.0', 'date' => '2026-03-02', 'notes' => 'Versión inicial', 'sections' => [self::datos(), ['title' => 'Evolución', 'fields' => [
                    self::campo('motivo', 'Motivo de la consulta de seguimiento', 'textarea', 'required'),
                    self::campo('cambio', 'Desde su última consulta se siente', 'single', 'required', ['Mejor', 'Igual', 'Peor']),
                    self::campo('dolor', 'Escala del dolor actual', 'scale', 'required'),
                    self::campo('adher', '¿Ha seguido el tratamiento indicado?', 'yesno', 'required'),
                    self::campo('efectos', 'Molestias o efectos adversos', 'textarea'),
                ]], self::medAlergias(), ['title' => 'Nuevos estudios', 'fields' => [
                    self::campo('archivos', 'Adjunte estudios recientes', 'files'),
                    self::campo('foto', 'Fotografía de la zona', 'photo'),
                ]]]],
            ]],
            'f3' => ['name' => 'Historia clínica de Ginecología', 'spec' => 'e2', 'type' => 'Primera consulta', 'versions' => [
                ['v' => '1.0', 'date' => '2026-04-10', 'notes' => 'Versión inicial', 'sections' => [self::datos(), self::motivo(), ['title' => 'Antecedentes gineco-obstétricos', 'fields' => [
                    self::campo('menarca', 'Edad de la primera menstruación (menarca)', 'number', 'required'),
                    self::campo('ciclos', 'Sus ciclos son', 'single', 'required', ['Regulares', 'Irregulares']),
                    self::campo('durac', 'Duración aproximada del ciclo (días)', 'number'),
                    self::campo('gest', 'Gestaciones', 'number'),
                    self::campo('partos', 'Partos', 'number'),
                    self::campo('ces', 'Cesáreas', 'number'),
                    self::campo('abortos', 'Abortos', 'number'),
                    self::campo('fum', 'Fecha de última menstruación', 'date', 'required'),
                    self::campo('anticon', 'Método anticonceptivo', 'select', 'optional', ['Ninguno', 'Hormonal oral', 'DIU', 'Implante', 'Preservativo', 'Otro']),
                ]], self::medAlergias(), self::estudios(), self::consentimiento()]],
            ]],
            'f4' => ['name' => 'Historia clínica de Neurología', 'spec' => 'e3', 'type' => 'Primera consulta', 'versions' => [
                ['v' => '1.0', 'date' => '2026-04-10', 'notes' => 'Versión inicial', 'sections' => [self::datos(), ['title' => 'Síntomas', 'fields' => [
                    self::campo('motivo', 'Motivo de consulta', 'textarea', 'required'),
                    self::campo('sint', 'Síntomas que presenta', 'multi', 'required', ['Dolor de cabeza', 'Mareo o vértigo', 'Hormigueo', 'Pérdida de fuerza', 'Convulsiones', 'Problemas de memoria', 'Otro']),
                    self::campo('inicio', 'Fecha aproximada de inicio', 'date'),
                    self::campo('evolu', '¿Cómo han evolucionado?', 'textarea'),
                    self::campo('intens', 'Intensidad de las molestias', 'scale'),
                ]], ['title' => 'Antecedentes neurológicos', 'fields' => [
                    self::campo('antneuro', 'Antecedentes neurológicos', 'textarea'),
                    self::campo('perso', 'Antecedentes personales', 'history', 'optional', self::ANTECEDENTES),
                ]], self::medAlergias(), self::estudios(), self::consentimiento()]],
            ]],
            'f5' => ['name' => 'Historia clínica general', 'spec' => null, 'type' => 'Primera consulta', 'versions' => [
                ['v' => '1.0', 'date' => '2026-03-02', 'notes' => 'Formato base para especialidades sin formato propio', 'sections' => [self::datos(), self::motivo(), self::antecedentes(), self::medAlergias(), self::estudios(), self::consentimiento()]],
            ]],
            'f6' => ['name' => 'Seguimiento general', 'spec' => null, 'type' => 'Consulta subsecuente', 'versions' => [
                ['v' => '1.0', 'date' => '2026-03-02', 'notes' => 'Versión inicial', 'sections' => [self::datos(), ['title' => 'Evolución', 'fields' => [
                    self::campo('motivo', 'Motivo de la consulta', 'textarea', 'required'),
                    self::campo('cambio', 'Desde su última consulta se siente', 'single', 'required', ['Mejor', 'Igual', 'Peor']),
                    self::campo('efectos', 'Cambios o molestias nuevas', 'textarea'),
                ]], self::medAlergias()]],
            ]],
            'f7' => ['name' => 'Valoración preoperatoria de Traumatología', 'spec' => 'e1', 'type' => 'Valoración preoperatoria', 'versions' => [
                ['v' => '1.0', 'date' => '2026-05-20', 'notes' => 'Versión inicial', 'sections' => [self::datos(), ['title' => 'Valoración preoperatoria', 'fields' => [
                    self::campo('motivo', 'Procedimiento programado', 'text', 'required'),
                    self::campo('anest', '¿Ha recibido anestesia anteriormente?', 'yesno', 'required'),
                    self::campo('reacc', '¿Tuvo alguna reacción a la anestesia?', 'textarea'),
                    self::campo('ayuno', 'Conozco y acepto las indicaciones de ayuno', 'yesno', 'required'),
                ]], self::medAlergias(), ['title' => 'Estudios preoperatorios', 'fields' => [
                    self::campo('archivos', 'Biometría, química sanguínea y tiempos de coagulación', 'files', 'required'),
                ]], self::consentimiento()]],
            ]],
        ];
    }
}
