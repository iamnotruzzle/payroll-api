using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Payroll.Mobile.Models.Dto;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.ViewModels;

public partial class PayslipsViewModel : ObservableObject
{
    private readonly IPayrollApi _api;

    public PayslipsViewModel(IPayrollApi api)
    {
        _api = api;
    }

    [ObservableProperty]
    private List<PayslipListItemDto> payslips = [];

    [ObservableProperty]
    private string message = string.Empty;

    [ObservableProperty]
    private bool isBusy;

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
            var result = await _api.GetPayslipsAsync();
            Payslips = result.Data;
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
    private Task OpenAsync(PayslipListItemDto? payslip)
    {
        if (payslip is null)
        {
            return Task.CompletedTask;
        }

        return Shell.Current.GoToAsync($"PayslipDetail?id={payslip.Id}");
    }
}
