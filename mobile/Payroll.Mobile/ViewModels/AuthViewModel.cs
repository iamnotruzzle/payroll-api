using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Payroll.Mobile.Models.Dto;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.ViewModels;

public partial class AuthViewModel : ObservableObject
{
    private readonly IPayrollApi _api;
    private readonly TokenStorageService _tokens;

    public AuthViewModel(IPayrollApi api, TokenStorageService tokens)
    {
        _api = api;
        _tokens = tokens;
    }

    [ObservableProperty]
    private string empId = string.Empty;

    [ObservableProperty]
    private string password = string.Empty;

    [ObservableProperty]
    private string errorMessage = string.Empty;

    [ObservableProperty]
    private bool isBusy;

    [RelayCommand]
    private async Task LoginAsync()
    {
        if (IsBusy)
        {
            return;
        }

        ErrorMessage = string.Empty;

        if (string.IsNullOrWhiteSpace(EmpId) || string.IsNullOrWhiteSpace(Password))
        {
            ErrorMessage = "Enter your employee ID and password.";
            return;
        }

        IsBusy = true;
        try
        {
            var response = await _api.LoginAsync(new LoginRequest
            {
                EmpId = EmpId.Trim(),
                Password = Password,
            });

            await _tokens.SetTokenAsync(response.Token);
            Password = string.Empty;
            await Shell.Current.GoToAsync("//home");
        }
        catch (Exception ex)
        {
            ErrorMessage = ApiError.Message(ex);
        }
        finally
        {
            IsBusy = false;
        }
    }
}
