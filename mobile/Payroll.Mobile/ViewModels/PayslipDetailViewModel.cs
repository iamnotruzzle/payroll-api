using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Payroll.Mobile.Models.Dto;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.ViewModels;

public partial class PayslipDetailViewModel : ObservableObject
{
    private readonly IPayrollApi _api;
    private readonly TokenStorageService _tokens;
    private readonly IApiBaseUrlProvider _urls;

    public PayslipDetailViewModel(IPayrollApi api, TokenStorageService tokens, IApiBaseUrlProvider urls)
    {
        _api = api;
        _tokens = tokens;
        _urls = urls;
    }

    [ObservableProperty]
    private int id;

    [ObservableProperty]
    private PayslipDetailDto? payslip;

    [ObservableProperty]
    private string? printHtml;

    [ObservableProperty]
    private string message = string.Empty;

    [ObservableProperty]
    private bool isBusy;

    partial void OnIdChanged(int value)
    {
        if (value > 0)
        {
            _ = LoadAsync();
        }
    }

    [RelayCommand]
    private async Task LoadAsync()
    {
        if (Id <= 0 || IsBusy)
        {
            return;
        }

        IsBusy = true;
        try
        {
            var detail = await _api.GetPayslipAsync(Id);
            Payslip = detail.Payslip;
            await LoadPrintHtmlAsync();
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

    private async Task LoadPrintHtmlAsync()
    {
        var token = await _tokens.GetTokenAsync();
        if (string.IsNullOrWhiteSpace(token) || Payslip is null)
        {
            return;
        }

        using var client = new HttpClient { BaseAddress = new Uri(_urls.Current + "/") };
        client.DefaultRequestHeaders.Authorization = new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", token);
        PrintHtml = await client.GetStringAsync($"/api/mobile/payslips/{Payslip.Id}/print");
    }
}
