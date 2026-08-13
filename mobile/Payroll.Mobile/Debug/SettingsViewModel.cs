using System.Diagnostics;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.Debug;

public partial class SettingsViewModel : ObservableObject
{
    private readonly IApiBaseUrlProvider _urls;

    public SettingsViewModel(IApiBaseUrlProvider urls)
    {
        _urls = urls;
        BaseUrl = _urls.Current;
    }

    [ObservableProperty]
    private string baseUrl = string.Empty;

    [ObservableProperty]
    private string statusMessage = string.Empty;

    [ObservableProperty]
    private bool isBusy;

    public string HotspotPreset => ApiSettings.Hotspot;

    public string EmulatorPreset => ApiSettings.Emulator;

    public string LocalPreset => ApiSettings.Local;

    public string HerdPreset => ApiSettings.Herd;

    [RelayCommand]
    private void UseHotspot() => BaseUrl = ApiSettings.Hotspot;

    [RelayCommand]
    private void UseEmulator() => BaseUrl = ApiSettings.Emulator;

    [RelayCommand]
    private void UseLocal() => BaseUrl = ApiSettings.Local;

    [RelayCommand]
    private void UseHerd() => BaseUrl = ApiSettings.Herd;

    [RelayCommand]
    private void Save()
    {
        if (!TryNormalize(BaseUrl, out var normalized, out var error))
        {
            StatusMessage = error;
            return;
        }

        _urls.Set(normalized);
        BaseUrl = normalized;
        StatusMessage = "Saved. API calls now use this URL.";
    }

    [RelayCommand]
    private async Task TestConnectionAsync()
    {
        if (IsBusy)
        {
            return;
        }

        if (!TryNormalize(BaseUrl, out var normalized, out var error))
        {
            StatusMessage = error;
            return;
        }

        IsBusy = true;
        StatusMessage = "Testing…";
        var stopwatch = Stopwatch.StartNew();
        try
        {
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(10) };
            using var response = await client.GetAsync(normalized + "/up");
            stopwatch.Stop();
            var body = await response.Content.ReadAsStringAsync();
            var snippet = body.Length > 80 ? body[..80] + "…" : body.Trim();
            StatusMessage = response.IsSuccessStatusCode
                ? $"OK {(int)response.StatusCode} in {stopwatch.ElapsedMilliseconds} ms"
                : $"HTTP {(int)response.StatusCode} in {stopwatch.ElapsedMilliseconds} ms. {snippet}";
        }
        catch (Exception ex)
        {
            stopwatch.Stop();
            StatusMessage = Describe(ex);
        }
        finally
        {
            IsBusy = false;
        }
    }

    private static bool TryNormalize(string? value, out string normalized, out string error)
    {
        normalized = ApiBaseUrlProvider.Normalize(value);
        if (normalized.Length == 0)
        {
            error = "Enter a base URL.";
            return false;
        }

        if (!Uri.TryCreate(normalized, UriKind.Absolute, out var uri)
            || (uri.Scheme != Uri.UriSchemeHttp && uri.Scheme != Uri.UriSchemeHttps))
        {
            error = "URL must start with http:// or https://";
            return false;
        }

        error = string.Empty;
        return true;
    }

    private static string Describe(Exception exception)
    {
        var current = exception;
        while (current.InnerException is not null)
        {
            current = current.InnerException;
        }

        return current.Message;
    }
}
