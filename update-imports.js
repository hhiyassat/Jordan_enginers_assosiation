const fs = require('fs');
const path = require('path');

function walk(dir) {
    let results = [];
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) {
            results = results.concat(walk(file));
        } else if (file.endsWith('.ts') || file.endsWith('.tsx')) {
            results.push(file);
        }
    });
    return results;
}

const files = walk('frontend/src');
files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    let newContent = content;
    
    // Global replace for outside api folder
    newContent = newContent.replace(/(['"])((?:\.\.\/)+)api\/http(['"])/g, '$1$2shared/api/http$3');
    
    // Inside api folder, ./http becomes ../shared/api/http
    if (file.match(/frontend[\\\/]src[\\\/]api[\\\/][^\/]+\.ts/)) {
        newContent = newContent.replace(/(['"])\.\/http(['"])/g, '$1../shared/api/http$2');
    }
    // Inside api/something folder, ../http becomes ../../shared/api/http
    if (file.match(/frontend[\\\/]src[\\\/]api[\\\/][^\\\/]+[\\\/][^\/]+\.ts/)) {
        newContent = newContent.replace(/(['"])\.\.\/http(['"])/g, '$1../../shared/api/http$2');
    }
    
    if (content !== newContent) {
        fs.writeFileSync(file, newContent, 'utf8');
        console.log(`Updated ${file}`);
    }
});
