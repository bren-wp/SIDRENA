using System.Text;

namespace Sidrena.Core;

public sealed record CatalogImportRow(
    string Kind,string Name,string Sku,string Barcode,string Unit,
    decimal CurrentPrice,decimal? AnchorPrice,string? AnchorDate,string? AnchorSource);

/// <summary>Explicit, read-only preview of SIDRENA's nine-column working CSV format.</summary>
public static class CatalogCsv
{
    public const string Header="VRSTA;NAZIV;SIFRA;BARKOD;JEDINICA;AKTUALNA_EUR;SIDRENA_EUR;DATUM_SIDRENE;IZVOR_SIDRENE";
    public static IReadOnlyList<CatalogImportRow> ReadPreview(string path)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(path);
        var info=new FileInfo(path);
        if(info.Length>5_000_000)throw new ArgumentException("CSV je prevelik (najviše 5 MB).");
        string data=File.ReadAllText(path,new UTF8Encoding(false,true));
        var records=Parse(data);
        if(records.Count==0||string.Join(";",records[0])!=Header)
            throw new ArgumentException("Pogrešan CSV format: očekuje se zaglavlje radnog izvoza SIDRENA.");
        if(records.Count<=1)throw new ArgumentException("Nema stavki za uvoz.");
        if(records.Count>5001)throw new ArgumentException("Najviše 5000 stavki po uvozu.");
        var rows=new List<CatalogImportRow>();
        for(int i=1;i<records.Count;i++)
        {
            var fields=records[i];
            if(fields.Count!=9)throw new ArgumentException($"Redak {i+1} nema točno devet polja.");
            try
            {
                decimal current=MoneyParser.Parse(fields[5]);
                decimal? anchor=string.IsNullOrWhiteSpace(fields[6])?null:MoneyParser.Parse(fields[6]);
                rows.Add(new CatalogImportRow(
                    CsvCellSecurity.Restore(fields[0]),CsvCellSecurity.Restore(fields[1]),
                    CsvCellSecurity.Restore(fields[2]),CsvCellSecurity.Restore(fields[3]),
                    CsvCellSecurity.Restore(fields[4]),current,anchor,
                    string.IsNullOrWhiteSpace(fields[7])?null:CsvCellSecurity.Restore(fields[7]),
                    string.IsNullOrWhiteSpace(fields[8])?null:CsvCellSecurity.Restore(fields[8])));
            }
            catch(ArgumentException ex){throw new ArgumentException($"Redak {i+1}: {ex.Message}",ex);}
        }
        return rows;
    }

    private static List<List<string>> Parse(string data)
    {
        var records=new List<List<string>>();
        var record=new List<string>();
        var field=new StringBuilder();
        bool quoted=false,afterQuote=false,fieldStart=true;
        void CompleteField()
        {
            if(field.Length>4096)throw new ArgumentException("CSV sadrži predugačko polje.");
            record.Add(field.ToString());field.Clear();fieldStart=true;afterQuote=false;
        }
        void CompleteRecord()
        {
            CompleteField();
            records.Add(record);
            if(records.Count>5001)throw new ArgumentException("CSV sadrži previše redaka.");
            record=new List<string>();
        }
        for(int i=0;i<data.Length;i++)
        {
            char c=data[i];
            if(c=='\0')throw new ArgumentException("CSV sadrži nedopušten kontrolni znak.");
            if(quoted)
            {
                if(c=='"')
                {
                    if(i+1<data.Length&&data[i+1]=='"'){field.Append('"');i++;}
                    else{quoted=false;afterQuote=true;}
                }
                else field.Append(c);
                continue;
            }
            if(c==';'){CompleteField();continue;}
            if(c is '\n' or '\r')
            {
                if(c=='\r'&&i+1<data.Length&&data[i+1]=='\n')i++;
                CompleteRecord();continue;
            }
            if(c=='"'&&fieldStart){quoted=true;fieldStart=false;continue;}
            if(c=='"'||afterQuote)throw new ArgumentException("CSV ima neispravne navodnike.");
            field.Append(c);fieldStart=false;
            if(field.Length>4096)throw new ArgumentException("CSV sadrži predugačko polje.");
        }
        if(quoted)throw new ArgumentException("CSV sadrži nezatvorene navodnike.");
        if(field.Length>0||record.Count>0||afterQuote)CompleteRecord();
        return records;
    }
}

/// <summary>
/// Guard spreadsheet CSV cells against formula execution while preserving exact re-import.
/// Every original leading apostrophe is doubled; formula-like fields get an apostrophe prefix.
/// </summary>
public static class CsvCellSecurity
{
    private static bool LooksFormulaLike(string text)
    {
        var trimmed=text.TrimStart(' ','\t','\r','\n');
        return trimmed.Length>0 && trimmed[0] is '=' or '+' or '-' or '@';
    }
    public static string Protect(string? text)
    {
        var value=text??"";
        return value.StartsWith('\'')||LooksFormulaLike(value) ? "'"+value : value;
    }
    public static string Restore(string value)
    {
        if(value.StartsWith("''",StringComparison.Ordinal))return value[1..];
        if(value.StartsWith("'",StringComparison.Ordinal)&&LooksFormulaLike(value[1..]))return value[1..];
        return value;
    }
}
