namespace Payroll.Mobile.Services;

public sealed class ApiBaseUrlProvider : IApiBaseUrlProvider
{
    public const string PreferenceKey = "api_base_url";

    public string Current
    {
        get
        {
            var saved = Preferences.Default.Get(PreferenceKey, string.Empty);
            return string.IsNullOrWhiteSpace(saved) ? ApiSettings.DefaultBaseUrl : Normalize(saved);
        }
    }

    public void Set(string url)
    {
        var normalized = Normalize(url);
        if (normalized.Length == 0)
        {
            Preferences.Default.Remove(PreferenceKey);
            return;
        }

        Preferences.Default.Set(PreferenceKey, normalized);
    }

    public static string Normalize(string? url)
    {
        return (url ?? string.Empty).Trim().TrimEnd('/');
    }
}
