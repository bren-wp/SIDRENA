using System.Threading;
using System.Windows;

namespace Sidrena.Desktop;

public partial class App : Application
{
    // Do not let a second desktop process modify a live database during restore.
    // A session-local named mutex also avoids two copies of the executable sharing
    // the same writable SQLite catalogue, its WAL and its sidecar files.
    private Mutex? _singleInstance;
    private bool _ownsSingleInstance;

    protected override void OnStartup(StartupEventArgs e)
    {
        _singleInstance=new Mutex(true,@"Local\Brendigo.SIDRENA.Desktop",out bool created);
        if(!created)
        {
            _singleInstance.Dispose();
            _singleInstance=null;
            MessageBox.Show("SIDRENA je već pokrenuta. Vratite se na postojeći prozor kako biste "+
                "izbjegli istodobno mijenjanje lokalnih podataka.",
                "SIDRENA — već pokrenuta",MessageBoxButton.OK,MessageBoxImage.Information);
            Shutdown();
            return;
        }
        _ownsSingleInstance=true;
        base.OnStartup(e);
    }

    protected override void OnExit(ExitEventArgs e)
    {
        if(_ownsSingleInstance)
        {
            _singleInstance?.ReleaseMutex();
            _ownsSingleInstance=false;
        }
        _singleInstance?.Dispose();
        base.OnExit(e);
    }
}
