using System.Globalization;
using System.Text.Json;

namespace Sidrena.Core;

/// <summary>Rules exchanged with the official WordPress SIDRENA public discovery API.
/// A valid contract describes legal dates; it never certifies a merchant's prices.</summary>
public sealed record OfficialLegalContract(
    string RulesRevision, DateOnly EffectiveFrom, DateOnly LegacyReferenceDate,
    DateOnly GeneralReferenceDate, int ArchiveMinDays, string PublicationDeadline)
{
    public string? ConsumerLawRevision { get; init; }
    public string? UnitPriceRuleRevision { get; init; }

    public static OfficialLegalContract? ParseOptional(JsonElement root)
    {
        if(!root.TryGetProperty("legal",out var section)||section.ValueKind==JsonValueKind.Null)
            return null; // Compatible with WordPress 1.5.0.
        if(section.ValueKind!=JsonValueKind.Object)
            throw new InvalidDataException("Pravni profil SIDRENA nije objekt.");

        string Required(JsonElement e,string field)
        {
            if(!e.TryGetProperty(field,out var prop)||prop.ValueKind!=JsonValueKind.String)
                throw new InvalidDataException("Nedostaje pravni podatak: "+field);
            return prop.GetString()??"";
        }
        DateOnly Iso(string text)
        {
            if(!DateOnly.TryParseExact(text,"yyyy-MM-dd",CultureInfo.InvariantCulture,DateTimeStyles.None,out var date))
                throw new InvalidDataException("Nevaljan referentni datum.");
            return date;
        }

        if(!section.TryGetProperty("schema",out var schema)||schema.ValueKind!=JsonValueKind.Number||
           schema.GetInt32()!=1 || Required(section,"jurisdiction")!="HR")
            throw new InvalidDataException("Nepodržani hrvatski pravni profil SIDRENA.");
        var revision=Required(section,"rules_revision");
        if(revision.Length is <5 or >80)throw new InvalidDataException("Revizija propisa nije valjana.");
        var effective=Iso(Required(section,"effective_from"));
        if(!section.TryGetProperty("reference_dates",out var refs)||refs.ValueKind!=JsonValueKind.Object)
            throw new InvalidDataException("Nedostaju referentni datumi.");
        var oldDate=Iso(Required(refs,"legacy_fmcg"));
        var newDate=Iso(Required(refs,"general_goods_services"));
        if(!section.TryGetProperty("archive_min_days",out var minDays)||
           minDays.ValueKind!=JsonValueKind.Number||
           !minDays.TryGetInt32(out var days)||days is <30 or >3650)
            throw new InvalidDataException("Pravno trajanje arhive nije valjano.");
        var deadline=Required(section,"product_publication_deadline");
        if(!TimeOnly.TryParseExact(deadline,"HH:mm",CultureInfo.InvariantCulture,DateTimeStyles.None,out _))
            throw new InvalidDataException("Nevaljano vrijeme objave.");
        string? OptionalRevision(string field)
        {
            if(!section.TryGetProperty(field,out var prop)||prop.ValueKind==JsonValueKind.Null)
                return null;
            if(prop.ValueKind!=JsonValueKind.String)
                throw new InvalidDataException("Pravni izvor nije valjan.");
            var value=prop.GetString();
            if(value is null || !System.Text.RegularExpressions.Regex.IsMatch(
                value,@"^NN-[0-9]{1,3}-[0-9]{4}$"))
                throw new InvalidDataException("Neispravna oznaka pravnog izvora.");
            return value;
        }
        return new OfficialLegalContract(revision,effective,oldDate,newDate,days,deadline)
        {
            ConsumerLawRevision=OptionalRevision("consumer_law_revision"),
            UnitPriceRuleRevision=OptionalRevision("unit_price_rule_revision")
        };
    }
}

public static class SidrenaLegalContract
{
    public const string Revision="NN-101-2026-NN-110-2026";
    public const string EffectiveFrom="2026-11-17";
    public const string LegacyReferenceDate="2025-05-02";
    public const string GeneralReferenceDate="2026-09-10";
    public const string ConsumerLawRevision="NN-59-2026";
    public const string UnitPriceRuleRevision="NN-105-2026";
    public static readonly string[] KnownReferenceDates={LegacyReferenceDate,GeneralReferenceDate};
}
