using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Payroll.Mobile.Models.Dto;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.ViewModels;

public partial class LeavesViewModel : ObservableObject
{
    private readonly IPayrollApi _api;

    public LeavesViewModel(IPayrollApi api)
    {
        _api = api;
        StartDate = DateTime.Today;
        EndDate = DateTime.Today;
    }

    [ObservableProperty]
    private List<LeaveDto> leaves = [];

    [ObservableProperty]
    private List<LeaveTypeDto> leaveTypes = [];

    [ObservableProperty]
    private LeaveTypeDto? selectedLeaveType;

    [ObservableProperty]
    private DateTime startDate;

    [ObservableProperty]
    private DateTime endDate;

    [ObservableProperty]
    private string applicantNote = string.Empty;

    [ObservableProperty]
    private string message = string.Empty;

    [ObservableProperty]
    private bool isBusy;

    [ObservableProperty]
    private bool showForm;

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
            var types = await _api.GetLeaveTypesAsync();
            LeaveTypes = types.Data;
            SelectedLeaveType ??= LeaveTypes.FirstOrDefault();

            var result = await _api.GetLeavesAsync("active");
            Leaves = result.Data;
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
    private void OpenForm()
    {
        ShowForm = true;
        Message = string.Empty;
    }

    [RelayCommand]
    private void CloseForm() => ShowForm = false;

    [RelayCommand]
    private async Task SubmitAsync()
    {
        if (IsBusy)
        {
            return;
        }

        if (SelectedLeaveType is null)
        {
            Message = "Choose a leave type.";
            return;
        }

        IsBusy = true;
        try
        {
            var response = await _api.CreateLeaveAsync(new LeaveCreateRequest
            {
                LeaveType = SelectedLeaveType.LeaveTypeId,
                DateMode = "weekdays",
                StartDate = StartDate.ToString("yyyy-MM-dd"),
                EndDate = EndDate.ToString("yyyy-MM-dd"),
                AutoSplitCredits = true,
                ApplicantNote = string.IsNullOrWhiteSpace(ApplicantNote) ? null : ApplicantNote.Trim(),
            });

            Message = response.Message;
            ShowForm = false;
            ApplicantNote = string.Empty;
            var result = await _api.GetLeavesAsync("active");
            Leaves = result.Data;
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
}
