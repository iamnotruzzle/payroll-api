using Payroll.Mobile.Models.Dto;
using Payroll.Mobile.ViewModels;

namespace Payroll.Mobile.Views;

public partial class PayslipsPage : ContentPage
{
    public PayslipsPage()
    {
        InitializeComponent();
        BindingContext = IPlatformApplication.Current!.Services.GetRequiredService<PayslipsViewModel>();
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();
        if (BindingContext is PayslipsViewModel viewModel)
        {
            await viewModel.LoadCommand.ExecuteAsync(null);
        }
    }

    private async void OnSelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (e.CurrentSelection.FirstOrDefault() is PayslipListItemDto item && BindingContext is PayslipsViewModel viewModel)
        {
            await viewModel.OpenCommand.ExecuteAsync(item);
            ((CollectionView)sender).SelectedItem = null;
        }
    }
}
