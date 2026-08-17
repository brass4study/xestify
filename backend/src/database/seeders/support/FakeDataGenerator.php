<?php

declare(strict_types=1);

namespace Xestify\database\seeders\support;

/**
 * Generates realistic Spanish demo business data (addresses, phones,
 * fictional company/brand names, domain-specific option pickers, note
 * templates) for STORY 10.6's business data seeder. Person identity data
 * lives in PersonDataGenerator, raw randomness in RandomPrimitives, dates
 * in DateRangeGenerator — split to keep each class under ~20 methods.
 */
final class FakeDataGenerator
{
    /** @var list<array{0:string,1:string,2:string}> [ciudad, provincia, prefijo_postal] */
    private const TOWNS = [
        ['Madrid', 'Madrid', '28'],
        ['Barcelona', 'Barcelona', '08'],
        ['Valencia', 'Valencia', '46'],
        ['Sevilla', 'Sevilla', '41'],
        ['Zaragoza', 'Zaragoza', '50'],
        ['Málaga', 'Málaga', '29'],
        ['Murcia', 'Murcia', '30'],
        ['Bilbao', 'Vizcaya', '48'],
        ['Vigo', 'Pontevedra', '36'],
        ['A Coruña', 'A Coruña', '15'],
        ['Alicante', 'Alicante', '03'],
        ['Córdoba', 'Córdoba', '14'],
        ['Valladolid', 'Valladolid', '47'],
        ['Vitoria-Gasteiz', 'Álava', '01'],
        ['Granada', 'Granada', '18'],
        ['Elche', 'Alicante', '03'],
        ['Oviedo', 'Asturias', '33'],
        ['Santander', 'Cantabria', '39'],
        ['Pamplona', 'Navarra', '31'],
        ['San Sebastián', 'Guipúzcoa', '20'],
        ['Cádiz', 'Cádiz', '11'],
        ['Salamanca', 'Salamanca', '37'],
    ];

    private const STREET_TYPES = ['Calle', 'Avenida', 'Plaza', 'Paseo', 'Camino'];

    private const STREET_NAMES = [
        'Mayor', 'de la Constitución', 'del Sol', 'Real', 'de la Estación',
        'del Carmen', 'de la Paz', 'de Cervantes', 'de Goya', 'de la Rosa',
        'del Pilar', 'de la Fuente', 'de los Álamos', 'del Prado', 'de la Iglesia',
        'de Colón', 'de Andalucía', 'del Mar', 'de la Libertad', 'de San Juan',
        'de los Olivos', 'del Puerto', 'de la Vega', 'del Castillo', 'de Levante',
    ];

    private const COMPANY_WORDS_A = [
        'Distribuciones', 'Suministros', 'Almacenes', 'Comercial', 'Grupo',
        'Central', 'Depósito', 'Importaciones', 'Mayoristas', 'Exclusivas',
    ];

    private const COMPANY_WORDS_B = [
        'Ópticas del Norte', 'Visión Ibérica', 'del Mediterráneo', 'Óptico Peninsular',
        'de Levante', 'Cantábrico', 'del Sur', 'Atlántico', 'Óptico Nacional',
        'Visión Global', 'del Ebro', 'Meseta', 'Costa Azul', 'de la Meseta',
    ];

    private const COMPANY_SUFFIXES = ['S.L.', 'S.A.', 'S.L.U.', 'S. Coop.'];

    private const BRAND_NAME_POOL = [
        'Visioptic', 'Lentia', 'OptiVista', 'ClarVue', 'PrimeLens', 'NordOptics',
        'SolarClear', 'EyeCraft', 'LumaVision', 'PureSight', 'CrystalEye', 'VisionPro',
        'OptiCore', 'ClearWave', 'FocusLine', 'BrightEye', 'NovaLens', 'SkyVision',
        'TrueSight', 'EchoOptics', 'ZenithLens', 'AuroraVision', 'PrismaEye',
        'MetroOptic', 'UrbanLens', 'SereneSight', 'VividVision', 'ApexOptics',
        'HorizonLens', 'StellarEye', 'CoastalVision', 'AtlasOptics', 'VelvetLens',
        'RadiantEye', 'OrbitVision', 'InfiniLens',
    ];

    private const MANUFACTURER_NAME_POOL = [
        'OptiTech Manufacturing', 'LensForge Industries', 'VisionCraft Labs',
        'ClearGlass Producciones', 'EuroLens Fabricación', 'NorthOptic Industrial',
        'PrimeGlass Manufactura', 'Ibérica de Lentes', 'Talleres Óptica del Sur',
        'Cristalería Visual S. Coop.', 'Manufacturas Ópticas Ebro',
        'Grupo Industrial Lensa', 'Fábrica de Lentes Atlántico',
        'Óptica Industrial Meseta', 'Producciones Visuales Delta',
        'Vidrio Óptico Peninsular', 'Cristal y Lente Ibérica',
        'Manufactura Óptica Sur', 'Talleres Visión Norte',
        'Industrias del Cristal Óptico',
    ];

    private const REPLACEMENT_SCHEDULES = [
        '', 'diario', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual',
    ];

    private const PACK_SIZES = ['', '1', '3', '6', '10', '30', '90'];

    /** Pool con repeticion deliberada para sesgar hacia 'entregado' (~60%). */
    private const ORDER_STATUS_POOL = [
        'pendiente', 'pendiente', 'pendiente',
        'en_proceso', 'en_proceso', 'en_proceso',
        'entregado', 'entregado', 'entregado', 'entregado', 'entregado', 'entregado',
        'entregado', 'entregado', 'entregado', 'entregado', 'entregado', 'entregado',
        'cancelado', 'cancelado',
    ];

    private const CLIENT_NOTES = [
        'Cliente habitual, prefiere avisos por teléfono.',
        'Solicita revisión de graduación cada 12 meses.',
        'Alergia leve a algunos materiales de montura.',
        'Prefiere cita a primera hora de la mañana.',
        'Cliente derivado por otro paciente de la tienda.',
        'Sin incidencias registradas hasta la fecha.',
        'Interesado en lentes progresivas para el próximo cambio.',
        'Prefiere que se le contacte por email.',
        'Cliente de la tienda desde hace varios años.',
        'Suele acudir acompañado de un familiar.',
    ];

    private const DISTRIBUTOR_NOTES = [
        'Plazo de entrega habitual de 3 a 5 días laborables.',
        'Pedido mínimo aplicable en algunos artículos.',
        'Buen historial de cumplimiento de plazos.',
        'Ofrece descuento por volumen a partir de cierto importe.',
        'Contacto preferente por teléfono en horario de mañana.',
        'Distribuidor habitual para reposición de stock.',
        'Requiere pedido por escrito antes de la entrega.',
        'Buena disponibilidad de referencias más vendidas.',
    ];

    private const OPHTHALMOLOGIST_NOTES = [
        'Colabora habitualmente con la tienda en derivaciones.',
        'Especialista en adaptación de lentillas.',
        'Consulta disponible solo por las tardes.',
        'Referente para casos de baja visión.',
        'Colegiado activo, revisiones periódicas.',
        'Buena disponibilidad para consultas urgentes.',
        'Trabaja habitualmente con pacientes derivados de la tienda.',
        'Sin incidencias registradas.',
    ];

    private const ORDER_NOTES = [
        'Pedido de reposición de stock habitual.',
        'Incluye referencias de temporada.',
        'Entrega parcial pendiente de confirmar.',
        'Pedido urgente por rotura de stock.',
        'Condiciones de pago habituales del distribuidor.',
        'Revisar cantidades antes de confirmar recepción.',
        'Pedido agrupado con otras referencias del mismo distribuidor.',
        'Sin incidencias en la recepción.',
    ];

    private const SALE_NOTES = [
        'Venta con entrega en tienda.',
        'Cliente solicitó factura detallada.',
        'Incluye revisión de graduación previa.',
        'Venta derivada de una revisión reciente.',
        'Pago fraccionado acordado con el cliente.',
        'Entrega coordinada con el cliente por teléfono.',
        'Sin incidencias en la venta.',
        'Cliente satisfecho con la atención recibida.',
    ];

    private const OPTOMETRY_NOTES = [
        'Graduación estable respecto a la revisión anterior.',
        'Ligero aumento de miopía respecto al historial previo.',
        'Paciente refiere fatiga visual ocasional.',
        'Se recomienda revisión de seguimiento en 12 meses.',
        'Buena tolerancia a la corrección actual.',
        'Primera ficha registrada para este paciente.',
        'Sin incidencias relevantes en la exploración.',
        'Se recomienda uso de gafas para tareas de cerca.',
    ];

    private const CONTACT_LENS_NOTES = [
        'Buena adaptación, sin molestias referidas.',
        'Paciente ya usuario habitual de lentillas.',
        'Primera adaptación, se recomienda revisión a las 2 semanas.',
        'Ligera sequedad ocular, se recomienda uso limitado.',
        'Cambio de marca por mejor tolerancia.',
        'Sin incidencias en la adaptación.',
        'Buena topografía corneal para el tipo de lentilla elegido.',
        'Se recomienda revisión anual de seguimiento.',
    ];

    private const COMMENT_BODIES = [
        'Cliente contactado para confirmar cita de revisión.',
        'Se ha resuelto una incidencia con un pedido anterior.',
        'Cliente satisfecho con la última compra.',
        'Pendiente de confirmar disponibilidad de un artículo.',
        'Se ha enviado presupuesto por email.',
        'Cliente solicita ser avisado ante nuevas ofertas.',
        'Nota interna: revisar historial antes de la próxima visita.',
        'Cliente recomendado por otro paciente de la tienda.',
        'Seguimiento tras la última adaptación de lentillas.',
        'Cliente ha confirmado asistencia a la próxima cita.',
    ];

    private function __construct()
    {
        // Clase estatica, instanciacion no permitida.
    }

    /**
     * @return array{0:string,1:string,2:string} [ciudad, provincia, prefijo_postal]
     */
    public static function townRecord(): array
    {
        return RandomPrimitives::pick(self::TOWNS);
    }

    public static function postalCode(string $prefix): string
    {
        return $prefix . str_pad((string) RandomPrimitives::intBetween(0, 999), 3, '0', STR_PAD_LEFT);
    }

    public static function streetAddress(): string
    {
        $type = RandomPrimitives::pick(self::STREET_TYPES);
        $name = RandomPrimitives::pick(self::STREET_NAMES);

        return $type . ' ' . $name . ', ' . RandomPrimitives::intBetween(1, 150);
    }

    public static function mobilePhone(): string
    {
        return sprintf('6%08d', RandomPrimitives::intBetween(0, 99999999));
    }

    public static function landlinePhone(): string
    {
        return sprintf('9%08d', RandomPrimitives::intBetween(0, 99999999));
    }

    public static function faxNumber(): string
    {
        return self::landlinePhone();
    }

    public static function webDomain(string $companyName): string
    {
        return PersonDataGenerator::slugify($companyName) . '.es';
    }

    public static function companyName(): string
    {
        return RandomPrimitives::pick(self::COMPANY_WORDS_A) . ' ' . RandomPrimitives::pick(self::COMPANY_WORDS_B);
    }

    public static function legalName(string $companyName): string
    {
        return $companyName . ' ' . RandomPrimitives::pick(self::COMPANY_SUFFIXES);
    }

    public static function clientCode(): string
    {
        return sprintf('CLI-%05d', RandomPrimitives::intBetween(1, 99999));
    }

    /**
     * @return list<string> $count nombres distintos, sin repetidos
     */
    public static function uniqueBrandNames(int $count): array
    {
        return RandomPrimitives::sampleWithoutReplacement(self::BRAND_NAME_POOL, $count);
    }

    /**
     * @return list<string> $count nombres distintos, sin repetidos
     */
    public static function uniqueManufacturerNames(int $count): array
    {
        return RandomPrimitives::sampleWithoutReplacement(self::MANUFACTURER_NAME_POOL, $count);
    }

    public static function ophthalmologistType(): string
    {
        return RandomPrimitives::boolWithProbability(60) ? 'ophthalmologist' : 'optometrist';
    }

    public static function ophthalmologistNumero(): string
    {
        return sprintf('COL-%02d-%05d', RandomPrimitives::intBetween(1, 52), RandomPrimitives::intBetween(1, 99999));
    }

    public static function replacementSchedule(): string
    {
        return RandomPrimitives::pick(self::REPLACEMENT_SCHEDULES);
    }

    public static function packSize(): string
    {
        return RandomPrimitives::pick(self::PACK_SIZES);
    }

    public static function orderStatus(): string
    {
        return RandomPrimitives::pick(self::ORDER_STATUS_POOL);
    }

    public static function noteFor(string $context): string
    {
        return RandomPrimitives::pick(self::notePoolFor($context));
    }

    /**
     * @return list<string>
     */
    private static function notePoolFor(string $context): array
    {
        return match ($context) {
            'client' => self::CLIENT_NOTES,
            'distributor' => self::DISTRIBUTOR_NOTES,
            'ophthalmologist' => self::OPHTHALMOLOGIST_NOTES,
            'order' => self::ORDER_NOTES,
            'sale' => self::SALE_NOTES,
            'optometry' => self::OPTOMETRY_NOTES,
            'contact_lens' => self::CONTACT_LENS_NOTES,
            'comment' => self::COMMENT_BODIES,
            default => [''],
        };
    }
}
