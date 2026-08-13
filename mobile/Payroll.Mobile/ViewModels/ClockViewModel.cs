using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Payroll.Mobile.Models.Dto;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.ViewModels;

public partial class ClockViewModel : ObservableObject
{
    private readonly IPayrollApi _api;
    private readonly TokenStorageService _tokens;

    public ClockViewModel(IPayrollApi api, TokenStorageService tokens)
    {
        _api = api;
        _tokens = tokens;
    }

    [ObservableProperty]
    private ClockStatusResponse? status;

    [ObservableProperty]
    private string message = string.Empty;

    [ObservableProperty]
    private bool isBusy;

    [ObservableProperty]
    private string? profileBanner;

    public string TimeInDisplay => FormatTime(Status?.Dtr?.TimeIn);

    public string TimeOutDisplay => FormatTime(Status?.Dtr?.TimeOut ?? Status?.Dtr?.TimeoutNextday);

    public string DateDisplay => Status?.Dtr?.DtrDate ?? Status?.Today ?? DateTime.Today.ToString("yyyy-MM-dd");

    public bool CanTimeIn => Status?.CanTimeIn == true;

    public bool CanTimeOut => Status?.CanTimeOut == true;

    partial void OnStatusChanged(ClockStatusResponse? value)
    {
        OnPropertyChanged(nameof(TimeInDisplay));
        OnPropertyChanged(nameof(TimeOutDisplay));
        OnPropertyChanged(nameof(DateDisplay));
        OnPropertyChanged(nameof(CanTimeIn));
        OnPropertyChanged(nameof(CanTimeOut));
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        if (IsBusy)
        {
            return;
        }

        IsBusy = true;
        try
        {
            Status = await _api.GetClockStatusAsync();

            var me = await _api.MeAsync();
            ProfileBanner = me.User.MustUpdateProfile
                ? "Please review your profile on the web portal when you can."
                : null;
        }
        catch (Exception ex)
        {
            Message = ApiError.Message(ex);
        }
        finally
        {
            IsBusy = false;
        }
    }

    [RelayCommand]
    private Task TimeInAsync() => PunchAsync("time_in");

    [RelayCommand]
    private Task TimeOutAsync() => PunchAsync("time_out");

    [RelayCommand]
    private async Task LogoutAsync()
    {
        try
        {
            await _api.LogoutAsync();
        }
        catch
        {
            // Still clear the local token if the server is unreachable.
        }

        _tokens.Clear();
        await Shell.Current.GoToAsync("//login");
    }

    private async Task PunchAsync(string punch)
    {
        if (IsBusy)
        {
            return;
        }

        IsBusy = true;
        Message = string.Empty;
        try
        {
            var permission = await Permissions.RequestAsync<Permissions.LocationWhenInUse>();
            if (permission != PermissionStatus.Granted)
            {
                Message = "Location permission is required to clock in.";
                return;
            }

            var location = await Geolocation.Default.GetLocationAsync(new GeolocationRequest(GeolocationAccuracy.Medium, TimeSpan.FromSeconds(15)));
            if (location is null)
            {
                Message = "Could not read your location. Enable location services and try again.";
                return;
            }

            var response = await _api.PunchAsync(new ClockPunchRequest
            {
                Punch = punch,
                Latitude = location.Latitude,
                Longitude = location.Longitude,
                DeviceTimestamp = DateTimeOffset.Now.ToString("O"),
            });

            Message = response.Message;
            await LoadUnlockedAsync();
        }
        catch (FeatureNotSupportedException)
        {
            Message = "Location is not supported on this device.";
        }
        catch (PermissionException)
        {
            Message = "Location permission is required to clock in.";
        }
        catch (Exception ex)
        {
            Message = ApiError.Message(ex);
        }
        finally
        {
            IsBusy = false;
        }
    }

    private async Task LoadUnlockedAsync()
    {
        Status = await _api.GetClockStatusAsync();
    }

    private static string FormatTime(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return "—";
        }

        return DateTime.TryParse(value, out var parsed) ? parsed.ToString("h:mm tt") : value;
    }
}
